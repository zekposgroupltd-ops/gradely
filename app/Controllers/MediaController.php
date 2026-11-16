<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{DB, Http};
use App\Services\Uploads;

/** Serves school logos from storage/ (outside the web root). Logos are public by design (they appear on report cards). */
final class MediaController
{
    public function logo(int $id): void
    {
        $rel = DB::val('SELECT logo_path FROM schools WHERE id = ?', [$id]);
        if (!is_string($rel) || !preg_match('#^logos/school-\d+\.png$#', $rel)) {
            Http::abort(404);
        }
        $file = Uploads::dir() . '/' . $rel;
        if (!is_file($file)) {
            Http::abort(404);
        }
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: public, max-age=3600');
        readfile($file);
    }
}
