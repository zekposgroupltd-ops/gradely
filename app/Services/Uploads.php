<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/** Secure image uploads. Files are validated, decoded and RE-ENCODED, so no uploaded bytes are ever served as-is. */
final class Uploads
{
    private const MAX_BYTES = 2_000_000;
    private const MAX_EDGE = 600;

    public static function dir(): string
    {
        return BASE_PATH . '/storage/uploads';
    }

    /** @return string path relative to storage/uploads, e.g. "logos/school-3.png". @throws \RuntimeException with a user-safe message */
    public static function saveLogo(array $file, int $schoolId): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Choose an image to upload.');
        }
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'That file is too large (maximum 2 MB).' : 'The upload failed. Please try again.');
        }
        $tmp = (string) $file['tmp_name'];
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp)) {
            throw new \RuntimeException('The upload failed. Please try again.');
        }
        if ((int) $file['size'] > self::MAX_BYTES || (int) filesize($tmp) > self::MAX_BYTES) {
            throw new \RuntimeException('That file is too large (maximum 2 MB).');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
            throw new \RuntimeException('The logo must be a PNG or JPG image.');
        }
        $info = @getimagesize($tmp);
        if ($info === false || $info[0] < 32 || $info[1] < 32 || $info[0] > 6000 || $info[1] > 6000) {
            throw new \RuntimeException('That image could not be read, or its dimensions are out of range.');
        }
        $src = @imagecreatefromstring((string) file_get_contents($tmp));
        if ($src === false) {
            throw new \RuntimeException('That image could not be read.');
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $k = min(1.0, self::MAX_EDGE / max($w, $h));
        $nw = max(1, (int) round($w * $k));
        $nh = max(1, (int) round($h * $k));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $dir = self::dir() . '/logos';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new \RuntimeException('The server could not store the file.');
        }
        $rel = 'logos/school-' . $schoolId . '.png';
        if (!imagepng($dst, self::dir() . '/' . $rel, 6)) {
            throw new \RuntimeException('The server could not store the file.');
        }
        return $rel;
    }
}
