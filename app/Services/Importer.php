<?php
declare(strict_types=1);

namespace App\Services;

/** Reads CSV and XLSX uploads into a list of rows keyed by normalised header names. XLSX is parsed natively (no libraries). */
final class Importer
{
    public const MAX_ROWS = 2000;
    private const MAX_BYTES = 2_000_000;
    private const MAX_UNZIPPED = 30_000_000;

    /**
     * @return array{headers:string[],rows:array<int,array<string,string>>}  rows are numbered from 2 (row 1 = header)
     * @throws \RuntimeException with a user-safe message
     */
    public static function read(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Choose a CSV or Excel (.xlsx) file to import.');
        }
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('The upload failed or the file is too large (maximum 2 MB).');
        }
        $tmp = (string) $file['tmp_name'];
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp)) {
            throw new \RuntimeException('The upload failed. Please try again.');
        }
        if ((int) filesize($tmp) > self::MAX_BYTES) {
            throw new \RuntimeException('The file is too large (maximum 2 MB).');
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $grid = match ($ext) {
            'csv', 'txt' => self::csv($tmp),
            'xlsx' => self::xlsx($tmp),
            default => throw new \RuntimeException('Only .csv and .xlsx files are supported.'),
        };
        return self::shape($grid);
    }

    /** @param array<int,array<int,string>> $grid */
    private static function shape(array $grid): array
    {
        while ($grid && self::blank($grid[0])) {
            array_shift($grid);
        }
        if (!$grid) {
            throw new \RuntimeException('The file is empty.');
        }
        $headers = array_map(static fn($h) => trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $h))), '_'), array_shift($grid));
        $rows = [];
        $n = 1;
        foreach ($grid as $cells) {
            $n++;
            if (self::blank($cells)) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $h) {
                if ($h !== '') {
                    $row[$h] = trim((string) ($cells[$i] ?? ''));
                }
            }
            $rows[$n] = $row;
            if (count($rows) > self::MAX_ROWS) {
                throw new \RuntimeException('Too many rows. Import at most ' . self::MAX_ROWS . ' rows at a time.');
            }
        }
        if (!$rows) {
            throw new \RuntimeException('The file has a header but no data rows.');
        }
        return ['headers' => array_values(array_filter($headers)), 'rows' => $rows];
    }

    private static function blank(array $cells): bool
    {
        foreach ($cells as $c) {
            if (trim((string) $c) !== '') {
                return false;
            }
        }
        return true;
    }

    private static function csv(string $path): array
    {
        $raw = (string) file_get_contents($path);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($raw, "\n") ?: '';
        $delim = ',';
        foreach ([';', "\t"] as $d) {
            if (substr_count($first, $d) > substr_count($first, $delim)) {
                $delim = $d;
            }
        }
        $h = fopen('php://temp', 'r+');
        fwrite($h, $raw);
        rewind($h);
        $grid = [];
        while (($r = fgetcsv($h, 0, $delim, '"', '')) !== false) {
            $grid[] = array_map('strval', $r);
            if (count($grid) > self::MAX_ROWS + 5) {
                break;
            }
        }
        fclose($h);
        return $grid;
    }

    private static function xlsx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Excel import is not available on this server. Please upload a CSV file instead.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('That does not look like a valid .xlsx file.');
        }
        try {
            $total = 0;
            $sheetName = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $total += (int) $st['size'];
                if ($total > self::MAX_UNZIPPED) {
                    throw new \RuntimeException('The Excel file is too large when unpacked.');
                }
                if ($sheetName === null && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $st['name'])) {
                    $sheetName = $st['name'];
                }
            }
            if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
                $sheetName = 'xl/worksheets/sheet1.xml';
            }
            if ($sheetName === null) {
                throw new \RuntimeException('No worksheet was found in that Excel file.');
            }

            $shared = [];
            $ssXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($ssXml !== false) {
                $ss = simplexml_load_string($ssXml, \SimpleXMLElement::class, LIBXML_NONET);
                if ($ss !== false) {
                    foreach ($ss->si as $si) {
                        $t = '';
                        foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $node) {
                            $t .= (string) $node;
                        }
                        $shared[] = $t;
                    }
                }
            }

            $xml = simplexml_load_string((string) $zip->getFromName($sheetName), \SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw new \RuntimeException('That Excel file could not be read.');
            }
            $grid = [];
            foreach ($xml->sheetData->row as $row) {
                $cells = [];
                $pos = 0;
                foreach ($row->c as $c) {
                    if (isset($c['r']) && preg_match('/^([A-Z]+)/', (string) $c['r'], $m)) {
                        $pos = self::colIndex($m[1]);
                    }
                    $type = (string) $c['t'];
                    $val = match ($type) {
                        's' => $shared[(int) $c->v] ?? '',
                        'inlineStr' => (string) ($c->is->t ?? ''),
                        'b' => ((string) $c->v) === '1' ? 'TRUE' : 'FALSE',
                        default => (string) $c->v,
                    };
                    $cells[$pos] = $val;
                    $pos++;
                }
                if ($cells) {
                    $max = max(array_keys($cells));
                    $filled = [];
                    for ($i = 0; $i <= $max; $i++) {
                        $filled[$i] = $cells[$i] ?? '';
                    }
                    $grid[] = $filled;
                } else {
                    $grid[] = [];
                }
                if (count($grid) > self::MAX_ROWS + 5) {
                    break;
                }
            }
            return $grid;
        } finally {
            $zip->close();
        }
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }
}
