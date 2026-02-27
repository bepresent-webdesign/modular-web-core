#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI test: Simulates Stripe webhook → Purchase → Fulfillment (PR #3).
 * Creates a fake Purchase (Stripe-like) and runs FulfillmentService->fulfill().
 * No real webhook call; validates the fulfillment flow used by the webhook.
 */

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "test_webhook_fulfillment_sim.php: failed to resolve project root\n");
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

require_once $projectRoot . '/lib/Secrets.php';
require_once $projectRoot . '/lib/Download/TokenService.php';
require_once $projectRoot . '/lib/Download/TokenStoreJson.php';

$appConfig = require $projectRoot . '/config/app.php';
$mailConfig = require $projectRoot . '/config/mail.php';
$productsPath = $projectRoot . '/config/products.php';
$licenseTypesPath = $projectRoot . '/config/license-types.php';

$storageRoot = $projectRoot . '/storage';
$fulfillmentPath = $storageRoot . '/fulfillment/fulfillment.json';
$purchasePath = $storageRoot . '/purchases/purchases.json';

$fulfillmentRepo = new \App\Infrastructure\Fulfillment\FulfillmentRepository($fulfillmentPath);
$purchaseRepo = new \App\Infrastructure\Purchases\PurchaseRepository($purchasePath);
$catalog = new \App\Domain\Catalog\ProductCatalog($productsPath, $licenseTypesPath);
$tokenService = new \TokenService(\Secrets::downloadTokenSecret());
$tokenStore = new \TokenStoreJson(CORE_ROOT . '/data/download_tokens.json');
$licenseKeyGen = new \App\Infrastructure\License\LicenseKeyGenerator();
$mailer = \App\Infrastructure\Mail\MailerFactory::fromConfig($mailConfig);

$fulfillmentService = new \App\Application\Fulfillment\FulfillmentService(
    $fulfillmentRepo,
    $catalog,
    $tokenService,
    $tokenStore,
    $licenseKeyGen,
    $mailer,
    $appConfig['base_url'] ?? 'https://example.com',
    $appConfig['download_path'] ?? '/public/download.php',
    CORE_ROOT . '/engine-version.json',
    $mailConfig['support_contact'] ?? '',
    $mailConfig['subject_prefix'] ?? '[Modular Web Core]',
);

$eventId = 'evt_sim_' . time();
$purchase = \App\Domain\Purchase\Purchase::create([
    'provider' => 'stripe',
    'provider_event_id' => $eventId,
    'customer_email' => 'sim-test@example.com',
    'product_id' => 'starter_business_card',
    'license_type' => 'standard_license',
    'amount' => 7900,
    'currency' => 'eur',
    'metadata' => ['provider_ref' => 'cs_sim_session', 'price_id' => 'price_test_starter_79'],
]);

$purchaseRepo->create($purchase);

echo "Webhook fulfillment sim\n";
echo "======================\n";
echo "Simulated Stripe event: {$eventId}\n";
echo "Purchase: {$purchase['purchase_id']}\n";
echo "Product: {$purchase['product_id']}\n\n";

$result = $fulfillmentService->fulfill($purchase);

echo "Fulfillment: " . ($result->success ? 'OK' : 'FAILED') . "\n";
if ($result->downloadUrl !== null && $result->downloadUrl !== '') {
    echo "Download URL: " . substr($result->downloadUrl, 0, 55) . "...\n";
}
if (!$result->success && $result->lastErrorMessage !== null) {
    fwrite(STDERR, "Error: {$result->lastErrorMessage}\n");
    exit(1);
}

$existing = $purchaseRepo->getByProviderEvent('stripe', $eventId);
if ($existing === null) {
    fwrite(STDERR, "FAIL: Purchase not found by provider event\n");
    exit(1);
}

echo "\nOK: Webhook fulfillment flow simulated successfully.\n";
exit(0);
