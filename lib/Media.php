<?php
declare(strict_types=1);

/**
 * Media: upload, WebP/thumb best-effort, trash. Max 10MB per image.
 * Nur JPG, PNG, WebP – keine SVG, keine Scripts.
 */
class Media {
    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const THUMB_MAX = 400;

    public static function upload(array $file, string $slot = ''): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Upload-Fehler: ' . $file['error']];
        }
        if ($file['size'] > self::MAX_SIZE) {
            return ['error' => 'Datei zu groß (max 10 MB).'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, self::ALLOWED_TYPES, true)) {
            return ['error' => 'Nur JPG, PNG, WebP erlaubt. Keine SVG oder ausführbaren Dateien.'];
        }
        ensure_dirs();
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $ext)) ?: 'jpg';
        $base = $slot ? preg_replace('/[^a-z0-9_-]/', '', $slot) . '_' : '';
        $name = $base . date('Ymd_His') . '_' . substr(uniqid(), -6) . '.' . $ext;
        $dir = UPLOADS_DIR . '/img';
        $path = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return ['error' => 'Datei konnte nicht gespeichert werden.'];
        }

        self::makeWebPAndThumb($path, $name);
        return ['ok' => true, 'path' => 'uploads/img/' . $name, 'name' => $name];
    }

    public static function moveToTrash(string $name): array {
        $safe = basename($name);
        if (preg_match('/^[a-zA-Z0-9._-]+$/', $safe) !== 1) {
            return ['error' => 'Ungültiger Dateiname.'];
        }
        $src = UPLOADS_DIR . '/img/' . $safe;
        $trashDir = UPLOADS_DIR . '/trash';
        if (!is_dir($trashDir)) mkdir($trashDir, 0755, true);
        $dest = $trashDir . '/' . date('Ymd_His') . '_' . $safe;
        if (!is_file($src)) {
            return ['error' => 'Datei nicht gefunden.'];
        }
        if (rename($src, $dest)) {
            foreach (['.webp', '_thumb.jpg', '_thumb.webp'] as $suffix) {
                $t = $src . $suffix;
                if (is_file($t)) rename($t, $dest . $suffix);
            }
            return ['ok' => true];
        }
        return ['error' => 'Verschieben fehlgeschlagen.'];
    }

    public static function replace(string $name, array $file): array {
        $del = self::moveToTrash($name);
        if (!empty($del['error'])) return $del;
        return self::upload($file, pathinfo($name, PATHINFO_FILENAME) ?: '');
    }

    public static function listImages(): array {
        $dir = UPLOADS_DIR . '/img';
        if (!is_dir($dir)) return [];
        $out = [];
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..' || $f === '.trash') continue;
            if (preg_match('/_thumb\.|\.webp$/i', $f)) continue;
            $full = $dir . '/' . $f;
            if (is_file($full) && preg_match('/\.(jpe?g|png|webp)$/i', $f)) {
                $out[] = ['name' => $f, 'path' => 'uploads/img/' . $f];
            }
        }
        /** 
         * usort($out, fn($a, $b) => strcmp($b['name'], $a['name']));
         * */
        usort($out, function($a, $b) {
            return strcmp($b['name'], $a['name']);
        });
        
        return $out;
    }

    private static function makeWebPAndThumb(string $path, string $name): void {
        $dir = dirname($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $webpPath = $dir . '/' . pathinfo($name, PATHINFO_FILENAME) . '.webp';
        $thumbPath = $dir . '/' . pathinfo($name, PATHINFO_FILENAME) . '_thumb.jpg';

        $img = null;
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $img = @imagecreatefromjpeg($path);
                break;
            case 'png':
                $img = @imagecreatefrompng($path);
                break;
            case 'webp':
                if (function_exists('imagecreatefromwebp')) {
                    $img = @imagecreatefromwebp($path);
                }
                break;
        }
        if (!$img) return;

        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($img);
            return;
        }

        if (function_exists('imagewebp')) {
            imagewebp($img, $webpPath, 85);
        }

        $scale = min(1, self::THUMB_MAX / max($w, $h));
        $tw = (int)round($w * $scale);
        $th = (int)round($h * $scale);
        if ($tw < 1) $tw = 1;
        if ($th < 1) $th = 1;
        $thumb = imagecreatetruecolor($tw, $th);
        if ($thumb) {
            imagecopyresampled($thumb, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
            imagejpeg($thumb, $thumbPath, 85);
            imagedestroy($thumb);
        }
        imagedestroy($img);
    }
}
