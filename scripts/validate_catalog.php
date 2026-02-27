#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI sanity check: instantiates ProductCatalog and validates all Stripe price mappings
 * via StripePurchaseValidator. Exit 0 = OK, 1 = errors.
 */

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "validate_catalog.php: failed to resolve project root\n");
    exit(1);
}

// Minimal autoload for app namespace
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

$errors = [];

try {
    $catalog = new \App\Domain\Catalog\ProductCatalog($productsPath, $licenseTypesPath);
} catch (\App\Domain\Exceptions\CatalogException $e) {
    $errors[] = "ProductCatalog: " . $e->getMessage();
}

try {
    $priceMap = new \App\Infrastructure\Payments\Stripe\StripePriceMap($pricesPath);
} catch (\App\Infrastructure\Payments\Stripe\StripePriceMapException $e) {
    $errors[] = "StripePriceMap: " . $e->getMessage();
}

if ($errors !== []) {
    foreach ($errors as $err) {
        fwrite(STDERR, $err . "\n");
    }
    exit(1);
}

$validator = new \App\Infrastructure\Payments\Stripe\StripePurchaseValidator($catalog);

$pricesData = require $pricesPath;
$priceIds = is_array($pricesData) ? array_keys($pricesData) : [];

foreach ($priceIds as $priceId) {
    try {
        $record = $priceMap->resolve($priceId);
        $intent = $validator->validate($record);
        // Ensure required keys present
        $required = ['product_id', 'license_type', 'max_downloads', 'includes_updates', 'update_months', 'amount_minor', 'currency', 'mode'];
        foreach ($required as $k) {
            if (!array_key_exists($k, $intent)) {
                $errors[] = "{$priceId}: PurchaseIntent missing key '{$k}'";
            }
        }
    } catch (\App\Infrastructure\Payments\Stripe\StripePriceMapException $e) {
        $errors[] = "{$priceId}: " . $e->getMessage();
    } catch (\App\Infrastructure\Payments\Stripe\StripePurchaseValidationException $e) {
        $errors[] = "{$priceId}: " . $e->getMessage();
    }
}

if ($errors !== []) {
    foreach ($errors as $err) {
        fwrite(STDERR, $err . "\n");
    }
    exit(1);
}

echo "OK: Product catalog and Stripe price mappings validated.\n";
exit(0);
