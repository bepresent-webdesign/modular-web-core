<?php
/**
 * API: Liste aller verfügbaren Bilder aus assets/img und uploads/img.
 * Liefert JSON: {assets: [{path, url}], uploads: [{path, url}]}
 * path = server-relativer Pfad (z.B. assets/img/hero.webp)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

Auth::requireAdmin();

$base = CORE_ROOT;
$assetsDir = $base . '/assets/img';
$uploadsDir = $base . '/uploads/img';

function listImagesInDir(string $dir, string $prefix): array {
    if (!is_dir($dir)) return [];
    $out = [];
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        if (preg_match('/_thumb\.(jpg|webp)$/i', $f)) continue;
        $full = $dir . '/' . $f;
        if (is_file($full) && preg_match('/\.(jpe?g|png|webp)$/i', $f)) {
            $path = $prefix . $f;
            $out[] = ['path' => $path, 'name' => $f];
        }
    }
    usort($out, fn($a, $b) => strcasecmp($b['name'], $a['name']));
    return $out;
}

$assets = listImagesInDir($assetsDir, 'assets/img/');
$uploads = listImagesInDir($uploadsDir, 'uploads/img/');

$baseUrl = rtrim(base_url(), '/');
$addUrl = function(array $arr) use ($baseUrl): array {
    return array_map(fn($i) => $i + ['url' => $baseUrl . '/' . $i['path']], $arr);
};

echo json_encode([
    'assets' => $addUrl($assets),
    'uploads' => $addUrl($uploads),
]);
