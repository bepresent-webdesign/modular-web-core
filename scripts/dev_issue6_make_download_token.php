#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Manual helper to mint a download token for engine_zip (Issue #6 testing).
 * Run: php scripts/dev_issue6_make_download_token.php
 * Never runs automatically.
 *
 * Prints a valid token and writes a matching record to TokenStoreJson with max_downloads=1.
 */

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Cannot resolve project root\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $projectRoot;
require_once $projectRoot . '/lib/bootstrap.php';
require_once CORE_ROOT . '/lib/Secrets.php';
require_once CORE_ROOT . '/lib/Download/TokenService.php';
require_once CORE_ROOT . '/lib/Download/TokenStoreJson.php';

$versionFile = CORE_ROOT . '/engine-version.json';
if (!is_readable($versionFile)) {
    fwrite(STDERR, "engine-version.json not found or not readable\n");
    exit(1);
}

$versionData = json_decode(file_get_contents($versionFile), true);
if (!is_array($versionData)) {
    fwrite(STDERR, "engine-version.json is invalid JSON\n");
    exit(1);
}

$engineVersion = $versionData['version'] ?? $versionData['engine_version'] ?? null;
if ($engineVersion === null || $engineVersion === '' || !is_string($engineVersion)) {
    fwrite(STDERR, "version or engine_version key missing in engine-version.json\n");
    exit(1);
}

if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.-]+)?$/', $engineVersion)) {
    fwrite(STDERR, "Invalid version format: {$engineVersion}\n");
    exit(1);
}

$secret = \Secrets::downloadTokenSecret();
$service = new \TokenService($secret);

$tokenId = 'dev_' . bin2hex(random_bytes(12));
$claims = [
    'token_id' => $tokenId,
    'exp' => time() + 3600,
    'file_key' => 'engine_zip',
    'engine_version' => $engineVersion,
];

$token = $service->makeToken($claims);

$store = new \TokenStoreJson();
$record = [
    'token_id' => $tokenId,
    'status' => 'active',
    'created_at' => date('c'),
    'exp' => $claims['exp'],
    'file_key' => 'engine_zip',
    'engine_version' => $engineVersion,
    'max_downloads' => 1,
    'download_count' => 0,
    'revoked_at' => null,
    'consumed_at' => null,
    'last_download_at' => null,
    'metadata' => [],
];
$store->put($record);

echo "Token (valid for 1 download):\n";
echo $token . "\n\n";
echo "Test with: curl -O -J \"BASE_URL/download.php?token=" . $token . "\"\n";
