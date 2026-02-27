<?php

declare(strict_types=1);

/**
 * Checkout Session Creator (Buy Flow).
 * GET /public/buy.php?product_id=...&license_type=...
 * Validates input, creates Stripe Checkout Session, redirects 303 to Stripe.
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

require_once CORE_ROOT . '/lib/Secrets.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    header('Content-Type: text/plain');
    echo 'Method not allowed';
    exit;
}

$productId = isset($_GET['product_id']) ? trim((string) $_GET['product_id']) : '';
$licenseType = isset($_GET['license_type']) ? trim((string) $_GET['license_type']) : '';

function respond400(string $message): never
{
    http_response_code(400);
    header('Content-Type: text/plain');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

try {
    $secretKey = \Secrets::stripeSecretKey();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Configuration error';
    exit;
}

$appConfig = require CORE_ROOT . '/config/app.php';
$baseUrl = rtrim($appConfig['base_url'] ?? 'https://example.com', '/');
$successPath = $appConfig['checkout_success_path'] ?? '/public/checkout/success.php';
$cancelPath = $appConfig['checkout_cancel_path'] ?? '/public/checkout/cancel.php';

$successUrl = $baseUrl . $successPath;
$cancelUrl = $baseUrl . $cancelPath;

$productsPath = CORE_ROOT . '/config/products.php';
$licenseTypesPath = CORE_ROOT . '/config/license-types.php';
$pricesPath = CORE_ROOT . '/config/prices.stripe.php';

$catalog = new \App\Domain\Catalog\ProductCatalog($productsPath, $licenseTypesPath);
$priceMap = new \App\Infrastructure\Payments\Stripe\StripePriceMap($pricesPath);
$service = new \App\Application\Checkout\StripeCheckoutService(
    $catalog,
    $priceMap,
    $secretKey,
    $successUrl,
    $cancelUrl,
);

try {
    $checkoutUrl = $service->createSession($productId, $licenseType);
} catch (\App\Application\Checkout\StripeCheckoutException $e) {
    $msg = $e->getMessage();
    if ($e->getCodeKey() === 'invalid_input') {
        $msg = 'product_id and license_type are required';
    } elseif ($e->getCodeKey() === 'invalid_product') {
        $msg = 'Product not found';
    } elseif ($e->getCodeKey() === 'invalid_license_type') {
        $msg = 'License type does not match product';
    } elseif ($e->getCodeKey() === 'no_price_mapping') {
        $msg = 'No Stripe price mapping for selection';
    }
    respond400($msg);
}

http_response_code(303);
header('Location: ' . $checkoutUrl);
header('Cache-Control: no-store');
exit;
