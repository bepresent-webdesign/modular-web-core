#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Manual smoketest for JSON token store (TokenStoreJson).
 * Run: php scripts/dev_token_store_smoketest.php
 * Never runs automatically.
 */

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Cannot resolve project root\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $projectRoot;
require_once $projectRoot . '/lib/bootstrap.php';
require_once CORE_ROOT . '/lib/Download/TokenStoreJson.php';

$store = new TokenStoreJson();

$tokenId = 'tok_test_' . bin2hex(random_bytes(8));
$record = [
    'token_id' => $tokenId,
    'status' => 'active',
    'created_at' => date('c'),
    'exp' => time() + 60,
    'file_key' => 'engine_zip',
    'engine_version' => '0.3.0',
    'max_downloads' => 1,
    'download_count' => 0,
    'revoked_at' => null,
    'consumed_at' => null,
    'last_download_at' => null,
    'metadata' => ['order_ref' => null, 'customer_ref' => null, 'payment_provider' => null],
];

$store->put($record);
echo "PUT OK\n";

$got = $store->get($tokenId);
if ($got === null || ($got['token_id'] ?? '') !== $tokenId) {
    echo "FAIL: get() returned wrong or null\n";
    exit(1);
}
echo "GET OK\n";

$consumed = $store->consume($tokenId);
if (($consumed['download_count'] ?? 0) !== 1 || ($consumed['status'] ?? '') !== 'consumed') {
    echo "FAIL: first consume - unexpected record state\n";
    exit(1);
}
echo "CONSUME 1 OK\n";

try {
    $store->consume($tokenId);
    echo "FAIL: second consume should have thrown\n";
    exit(1);
} catch (Throwable $e) {
    echo "SECOND CONSUME OK\n";
}

// Revoke test on new token
$tokenId2 = 'tok_test_' . bin2hex(random_bytes(8));
$record2 = [
    'token_id' => $tokenId2,
    'status' => 'active',
    'created_at' => date('c'),
    'exp' => time() + 60,
    'file_key' => 'engine_zip',
    'engine_version' => '0.3.0',
    'max_downloads' => 1,
    'download_count' => 0,
    'revoked_at' => null,
    'consumed_at' => null,
    'last_download_at' => null,
    'metadata' => [],
];
$store->put($record2);
$revoked = $store->revoke($tokenId2, 'test_reason');
if (($revoked['status'] ?? '') !== 'revoked' || empty($revoked['revoked_at'])) {
    echo "FAIL: revoke - status or revoked_at not set\n";
    exit(1);
}
echo "REVOKE OK\n";

echo "All smoketests passed.\n";
exit(0);
