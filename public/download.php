<?php

declare(strict_types=1);

/**
 * Secure token-based download endpoint.
 * GET /download.php?token=...
 *
 * Verifies token via TokenService, consumes via TokenStoreJson, streams ZIP from dist/.
 * Maps token claims to allowed file paths only (no user-controlled paths).
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
if ($token === '') {
    http_response_code(404);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    exit;
}

define('CORE_ROOT', dirname(__DIR__));
require_once CORE_ROOT . '/lib/Secrets.php';
require_once CORE_ROOT . '/lib/Download/TokenService.php';
require_once CORE_ROOT . '/lib/Download/TokenStoreJson.php';

try {
    $secret = \Secrets::downloadTokenSecret();
    $service = new \TokenService($secret);
    $claims = $service->verify($token);
} catch (Throwable $e) {
    send404();
}

$tokenId = $claims['token_id'] ?? '';
$fileKey = $claims['file_key'] ?? '';
$engineVersion = $claims['engine_version'] ?? '';

if (!is_string($tokenId) || $tokenId === '' || !is_string($fileKey) || !is_string($engineVersion)) {
    send404();
}

$allowedFileKeys = ['engine_zip' => true];
if (!isset($allowedFileKeys[$fileKey])) {
    send404();
}

if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.-]+)?$/', $engineVersion)) {
    send404();
}

$candidatePath = CORE_ROOT . '/dist/modular-web-core-' . $engineVersion . '.zip';
$realPath = realpath($candidatePath);
$distDir = realpath(CORE_ROOT . '/dist');

if ($realPath === false || $distDir === false || !str_starts_with($realPath, $distDir . DIRECTORY_SEPARATOR)) {
    send404();
}
if (!is_readable($realPath)) {
    send404();
}

try {
    $store = new \TokenStoreJson();
    $store->consume($tokenId);
} catch (Throwable $e) {
    send404();
}

$size = filesize($realPath);
if ($size === false) {
    send404();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="modular-web-core-' . $engineVersion . '.zip"');
header('Content-Length: ' . $size);

if (ob_get_level()) {
    ob_end_clean();
}
readfile($realPath);
exit;

function send404(): never
{
    http_response_code(404);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    exit;
}
