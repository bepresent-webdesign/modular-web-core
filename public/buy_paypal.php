<?php

declare(strict_types=1);

/**
 * PayPal Checkout – Buy Flow.
 * GET /buy_paypal.php?product_id=...
 * Creates order, redirects to PayPal approval URL.
 * No cookies, no sessions.
 */

define('CORE_ROOT', dirname(__DIR__));
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

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    header('Content-Type: text/plain');
    echo 'Method not allowed';
    exit;
}

$productId = isset($_GET['product_id']) ? trim((string) $_GET['product_id']) : '';

function respond400(string $message): never
{
    http_response_code(400);
    header('Content-Type: text/plain');
    header('Cache-Control: no-store');
    echo $message;
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

$productsPath = CORE_ROOT . '/config/products.php';
$licenseTypesPath = CORE_ROOT . '/config/license-types.php';
$catalog = new \App\Domain\Catalog\ProductCatalog($productsPath, $licenseTypesPath);

$service = new PayPalCheckoutService(
    $clientId,
    $secret,
    $webhookId,
    $sandbox,
    $baseUrl . $returnPath,
    $baseUrl . $cancelPath,
);

try {
    $approvalUrl = $service->createOrder($productId, $catalog);
} catch (\App\Domain\Exceptions\CatalogException $e) {
    respond400('Product not found');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Checkout error';
    exit;
}

http_response_code(303);
header('Location: ' . $approvalUrl);
header('Cache-Control: no-store');
exit;
