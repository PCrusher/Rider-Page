<?php
declare(strict_types=1);

/**
 * Safe image uploads: every file is decoded and re-encoded with GD, so nothing but pixels
 * ends up in /uploads. Names are random. SVG is not accepted.
 */
final class Images
{
    private const MAX_BYTES = 15 * 1024 * 1024;
    private const MAX_PIXELS = 50_000_000;
    private const LARGE = 1800;
    public const SIZES = [1200, 1600, 1800, 2400];
    private const THUMB = 800;

    public static function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    /** Longest side of the pictures shown on the page (a setting, 1800 by default). */
    public static function maxSize(): int
    {
        $v = (int)Settings::get('img_max', (string)self::LARGE);
        return in_array($v, self::SIZES, true) ? $v : self::LARGE;
    }

    public static function quality(): int
    {
        return max(60, min(95, (int)Settings::get('img_quality', '84')));
    }

    public static function keepOriginals(): bool
    {
        return Settings::get('img_keep', '1') !== '0';
    }

    public static function originalsDir(): string
    {
        return RP_STORAGE . '/originals';
    }

    /** The name part of uploads/ab12.jpg, or '' when the path is not one of ours. */
    private static function nameOf(string $rel): string
    {
        return preg_match('~^uploads/([a-f0-9]{16})(?:-800)?\.(?:jpg|png)$~', $rel, $m) ? $m[1] : '';
    }

    /** @return array{path:string,ext:string,name:string}|null the stored original for a picture path or a bare name */
    public static function original(string $relOrName): ?array
    {
        $n = preg_match('/^[a-f0-9]{16}$/', $relOrName) ? $relOrName : self::nameOf($relOrName);
        if ($n === '') {
            return null;
        }
        foreach (['jpg', 'png', 'gif', 'webp'] as $ext) {
            $f = self::originalsDir() . '/' . $n . '.' . $ext;
            if (is_file($f)) {
                $side = self::originalsDir() . '/' . $n . '.name';
                $orig = is_file($side) ? trim((string)file_get_contents($side)) : '';
                return ['path' => $f, 'ext' => $ext, 'name' => $orig !== '' ? $orig : $n . '.' . $ext];
            }
        }
        return null;
    }

    public static function deleteOriginal(string $relOrName): void
    {
        $o = self::original($relOrName);
        if ($o) {
            @unlink($o['path']);
            @unlink(self::originalsDir() . '/' . substr(basename($o['path']), 0, 16) . '.name');
        }
    }

    /** @return array<int,array> every stored original, newest first */
    public static function originals(): array
    {
        $out = [];
        foreach (@scandir(self::originalsDir()) ?: [] as $base) {
            $f = self::originalsDir() . '/' . $base;
            $n = substr($base, 0, 16);
            if (!preg_match('/^[a-f0-9]{16}\.(jpg|png|gif|webp)$/', $base) || !is_file($f)) {
                continue;
            }
            $o = self::original($n);
            $page = '';
            foreach (['jpg', 'png'] as $e) {
                if (is_file(RP_ROOT . '/uploads/' . $n . '.' . $e)) {
                    $page = 'uploads/' . $n . '.' . $e;
                }
            }
            $out[] = ['name' => $n, 'file' => $o['name'] ?? basename($f), 'size' => (int)filesize($f), 'time' => (int)filemtime($f), 'page' => $page];
        }
        usort($out, static fn($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    public static function thumb(string $rel): string
    {
        return preg_replace('/(\.[a-z0-9]+)$/i', '-' . self::THUMB . '$1', $rel) ?? $rel;
    }

    /**
     * @param array $file one entry of $_FILES
     * @param bool  $keepAlpha keep transparency (PNG) for logos, otherwise JPEG
     * @return string path relative to the install folder, e.g. uploads/ab12cd34.jpg
     */
    public static function fromUpload(array $file, bool $keepAlpha = false): string
    {
        if (!self::available()) {
            throw new RuntimeException('Image uploads need the PHP GD extension, which is not enabled on this server.');
        }
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('The file is larger than the server allows. Try a smaller image.');
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new RuntimeException('The upload did not work. Please try again.');
        }
        return self::process((string)$file['tmp_name'], $keepAlpha, (string)($file['name'] ?? ''));
    }

    /** Same as fromUpload but for a file already on disk (used for tests and seeding). */
    public static function process(string $path, bool $keepAlpha = false, string $origName = ''): string
    {
        if (!is_file($path) || filesize($path) > self::MAX_BYTES) {
            throw new RuntimeException('The image is too large (15 MB at most).');
        }
        $info = @getimagesize($path);
        if ($info === false) {
            throw new RuntimeException('This file is not an image. Use JPG, PNG, WebP or GIF.');
        }
        [$w, $h, $type] = $info;
        if ($w * $h > self::MAX_PIXELS) {
            throw new RuntimeException('The image has too many pixels. Resize it to under 50 megapixels first.');
        }
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (!$src) {
            throw new RuntimeException('This image format is not supported. Use JPG, PNG, WebP or GIF.');
        }
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $o = (int)($exif['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($angle !== 0) {
                $rot = imagerotate($src, $angle, 0);
                if ($rot) {
                    $src = $rot;
                }
            }
        }

        if (!is_dir(RP_UPLOADS)) {
            @mkdir(RP_UPLOADS, 0755, true);
        }
        $ext = $keepAlpha ? 'png' : 'jpg';
        $name = bin2hex(random_bytes(8));
        $rel = 'uploads/' . $name . '.' . $ext;

        if (self::keepOriginals()) {
            // The untouched file is kept out of reach of the web, only logged-in staff can download it.
            $dir = self::originalsDir();
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            $oext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$type] ?? 'jpg';
            if (is_dir($dir) && @copy($path, $dir . '/' . $name . '.' . $oext)) {
                $clean = trim((string)preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename($origName)), '. ');
                @file_put_contents($dir . '/' . $name . '.name', mb_substr($clean, 0, 120));
            }
        }
        self::save($src, self::maxSize(), RP_ROOT . '/' . $rel, $keepAlpha);
        self::save($src, self::THUMB, RP_ROOT . '/' . self::thumb($rel), $keepAlpha);
        return $rel;
    }

    private static function save(\GdImage $src, int $max, string $dest, bool $keepAlpha): void
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($keepAlpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        } else {
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = $keepAlpha ? imagepng($dst, $dest, 7) : imagejpeg($dst, $dest, self::quality());
        if (!$ok) {
            throw new RuntimeException('The image could not be saved. Check that the uploads folder is writable.');
        }
        @chmod($dest, 0644);
    }
}
