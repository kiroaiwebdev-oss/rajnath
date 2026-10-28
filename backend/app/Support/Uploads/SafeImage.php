<?php

namespace App\Support\Uploads;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Accepts an uploaded image only if PHP's GD can decode it as JPEG/PNG/WebP, then re-encodes it
 * to JPEG. Re-encoding drops EXIF (including GPS location), embedded scripts and polyglot payloads.
 * Client-supplied names and MIME types are never trusted.
 */
class SafeImage
{
    public const MAX_BYTES = 8 * 1024 * 1024;
    public const MAX_SIDE = 2400;

    public static function store(UploadedFile $file, string $disk, string $dir, string $field = 'photo'): string
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([$field => 'Upload a photo under 8 MB.']);
        }
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages([$field => 'Upload a JPG, PNG or WebP photo.']);
        }
        if ($info[0] < 400 || $info[1] < 400) {
            throw ValidationException::withMessages([$field => 'The photo is too small. Please take a clearer, closer photo (at least 400×400 pixels).']);
        }
        if ($info[0] * $info[1] > 40_000_000) {
            throw ValidationException::withMessages([$field => 'The photo resolution is too large.']);
        }
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            throw ValidationException::withMessages([$field => 'This photo could not be read. Please try another.']);
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
            $src = match ((int) $o) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $dst = imagecreatetruecolor((int) round($w * $scale), (int) round($h * $scale));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        ob_start();
        imagejpeg($dst, null, 86);
        $bytes = (string) ob_get_clean();
        $path = trim($dir, '/').'/'.Str::ulid().'.jpg';
        Storage::disk($disk)->put($path, $bytes);

        return $path;
    }
}
