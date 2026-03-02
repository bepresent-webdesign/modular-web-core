<?php

declare(strict_types=1);

/**
 * PayPal return handler. User returns after approving payment.
 * Captures order, shows "Payment processing".
 * Fulfillment happens via webhook.
 */

define('CORE_ROOT', dirname(__DIR__, 2));
chdir(CORE_ROOT);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = CORE_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once CORE_ROOT . '/lib/Payment/PayPalCheckoutService.php';

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
if ($token === '') {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Missing token';
    exit;
}

$config = require CORE_ROOT . '/config/paypal.php';
if (!is_array($config)) {
    http_response_code(500);
    echo 'Configuration error';
    exit;
}

$clientId = $config['client_id'] ?? '';
$secret = $config['secret'] ?? '';
$webhookId = $config['webhook_id'] ?? '';
$sandbox = (bool) ($config['sandbox'] ?? true);

if ($clientId === '' || $secret === '') {
    http_response_code(500);
    echo 'PayPal not configured';
    exit;
}

$appConfig = require CORE_ROOT . '/config/app.php';
$baseUrl = rtrim($appConfig['base_url'] ?? 'https://example.com', '/');
$returnPath = $appConfig['paypal_return_path'] ?? '/public/paypal/return.php';
$cancelPath = $appConfig['paypal_cancel_path'] ?? '/public/checkout/cancel.php';

$service = new PayPalCheckoutService(
    $clientId,
    $secret,
    $webhookId,
    $sandbox,
    $baseUrl . $returnPath,
    $baseUrl . $cancelPath,
);

try {
    $service->captureOrder($token);
} catch (Throwable) {
    // Idempotent: already-captured orders may throw; treat as success.
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Payment processing</title></head><body><p>Payment processing. You will receive your license and download link by email shortly.</p></body></html>';
