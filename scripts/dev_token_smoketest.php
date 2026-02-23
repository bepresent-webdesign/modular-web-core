#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Manual smoketest for download token HMAC service.
 * Run: php scripts/dev_token_smoketest.php
 * Never runs automatically.
 */

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Cannot resolve project root\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $projectRoot; // bootstrap may use it
require_once $projectRoot . '/lib/bootstrap.php';
require_once CORE_ROOT . '/lib/Secrets.php';
require_once CORE_ROOT . '/lib/Download/TokenService.php';

$secret = download_token_secret();
$service = new TokenService($secret);

$claims = [
    'token_id' => 'test-' . bin2hex(random_bytes(4)),
    'exp' => time() + 600,
    'file_key' => 'uploads/img/example.jpg',
    'engine_version' => '0.3.0',
];

$token = $service->makeToken($claims);
$verified = $service->verify($token);

if (($verified['token_id'] ?? '') === $claims['token_id']) {
    echo "OK " . $claims['token_id'] . "\n";
} else {
    echo "FAIL: token_id mismatch\n";
    exit(1);
}

// Tamper: flip one char in the signature part (middle index) so decode differs
[$p, $s] = explode('.', $token, 2);
$idx = max(0, (int) (strlen($s) / 2));
$s[$idx] = ($s[$idx] === 'x' ? 'y' : 'x');
$tampered = $p . '.' . $s;
try {
    $service->verify($tampered);
    echo "FAIL: tampered token should have been rejected\n";
    exit(1);
} catch (Throwable $e) {
    echo "TAMPER OK\n";
}

exit(0);
