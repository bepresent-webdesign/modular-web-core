#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI test: Checkout input validation (no real Stripe call).
 * Verifies product_id/license_type whitelist and price mapping.
 */

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "test_checkout_validation.php: failed to resolve project root\n");
    exit(1);
}

define('CORE_ROOT', $projectRoot);
chdir($projectRoot);

spl_autoload_register(static function (string $class) use ($projectRoot): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $projectRoot . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$productsPath = $projectRoot . '/config/products.php';
$licenseTypesPath = $projectRoot . '/config/license-types.php';
$pricesPath = $projectRoot . '/config/prices.stripe.php';

$catalog = new \App\Domain\Catalog\ProductCatalog($productsPath, $licenseTypesPath);
$priceMap = new \App\Infrastructure\Payments\Stripe\StripePriceMap($pricesPath);

$service = new \App\Application\Checkout\StripeCheckoutService(
    $catalog,
    $priceMap,
    'sk_test_dummy',
    'https://example.com/success',
    'https://example.com/cancel',
);

$errors = 0;

try {
    $service->createSession('', 'standard_license');
    fwrite(STDERR, "FAIL: empty product_id should throw\n");
    $errors++;
} catch (\App\Application\Checkout\StripeCheckoutException $e) {
    if ($e->getCodeKey() !== 'invalid_input') {
        fwrite(STDERR, "FAIL: expected invalid_input, got {$e->getCodeKey()}\n");
        $errors++;
    }
}

try {
    $service->createSession('nonexistent_product', 'standard_license');
    fwrite(STDERR, "FAIL: nonexistent product should throw\n");
    $errors++;
} catch (\App\Application\Checkout\StripeCheckoutException $e) {
    if ($e->getCodeKey() !== 'invalid_product') {
        fwrite(STDERR, "FAIL: expected invalid_product, got {$e->getCodeKey()}\n");
        $errors++;
    }
}

try {
    $service->createSession('starter_business_card', 'wrong_license');
    fwrite(STDERR, "FAIL: wrong license_type should throw\n");
    $errors++;
} catch (\App\Application\Checkout\StripeCheckoutException $e) {
    if ($e->getCodeKey() !== 'invalid_license_type') {
        fwrite(STDERR, "FAIL: expected invalid_license_type, got {$e->getCodeKey()}\n");
        $errors++;
    }
}

$found = $priceMap->findByProductId('starter_business_card');
if ($found === null) {
    fwrite(STDERR, "FAIL: price mapping for starter_business_card expected\n");
    $errors++;
} elseif ($found['price_id'] !== 'price_test_starter_79') {
    fwrite(STDERR, "FAIL: wrong price_id\n");
    $errors++;
}

if ($errors > 0) {
    exit(1);
}

echo "OK: Checkout validation tests passed.\n";
exit(0);
