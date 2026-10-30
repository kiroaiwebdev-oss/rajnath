<?php

namespace App\Support\Uploads;

use GdImage;
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

    public const THUMB_SIDE = 720;

    public static function store(UploadedFile $file, string $disk, string $dir, string $field = 'photo'): string
    {
        $path = trim($dir, '/').'/'.Str::ulid().'.jpg';
        Storage::disk($disk)->put($path, self::jpeg(self::resize(self::decode($file, $field), self::MAX_SIDE)));

        return $path;
    }

    /**
     * Public web image: a full-size JPEG (longest side 2400 px) and a thumbnail (720 px), each with a
     * WebP sibling that the <x-photo> component serves to browsers that support it.
     *
     * @return array{path: string, thumb: string, width: int, height: int}
     */
    public static function storeForWeb(UploadedFile $file, string $disk, string $dir, string $field = 'photo'): array
    {
        $src = self::decode($file, $field);
        $base = trim($dir, '/').'/'.Str::ulid();
        $full = self::resize($src, self::MAX_SIDE);
        $thumb = self::resize($src, self::THUMB_SIDE);
        $d = Storage::disk($disk);
        $d->put($base.'.jpg', self::jpeg($full));
        $d->put($base.'-thumb.jpg', self::jpeg($thumb, 82));
        if (function_exists('imagewebp')) {
            $d->put($base.'.webp', self::webp($full));
            $d->put($base.'-thumb.webp', self::webp($thumb, 78));
        }

        return ['path' => $base.'.jpg', 'thumb' => $base.'-thumb.jpg', 'width' => imagesx($full), 'height' => imagesy($full)];
    }

    /** Deletes a stored web image and its thumbnail and WebP siblings. */
    public static function deleteForWeb(string $disk, ?string $path): void
    {
        if (! $path) {
            return;
        }
        $base = preg_replace('/(-thumb)?\.jpg$/', '', $path);
        Storage::disk($disk)->delete([$base.'.jpg', $base.'.webp', $base.'-thumb.jpg', $base.'-thumb.webp']);
    }

    private static function decode(UploadedFile $file, string $field): GdImage
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
            $src = match ((int) $o) {
                3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src
            };
        }

        return $src;
    }

    private static function resize(GdImage $src, int $maxSide): GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxSide / max($w, $h));
        $dst = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);

        return $dst;
    }

    private static function jpeg(GdImage $img, int $quality = 86): string
    {
        ob_start();
        imagejpeg($img, null, $quality);

        return (string) ob_get_clean();
    }

    private static function webp(GdImage $img, int $quality = 80): string
    {
        ob_start();
        imagewebp($img, null, $quality);

        return (string) ob_get_clean();
    }
}
