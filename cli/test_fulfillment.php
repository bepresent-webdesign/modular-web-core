#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI test: FulfillmentService (PR #2)
 * - Creates Purchase
 * - Calls fulfill() (Mailer runs in pretend mode by default)
 * - Outputs status transitions + truncated download URL
 */

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "test_fulfillment.php: failed to resolve project root\n");
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
$tokenStorePath = $projectRoot . '/data/download_tokens.json';
$engineVersionPath = $projectRoot . '/engine-version.json';

$fulfillmentRepo = new \App\Infrastructure\Fulfillment\FulfillmentRepository($fulfillmentPath);
$purchaseRepo = new \App\Infrastructure\Purchases\PurchaseRepository($purchasePath);
$catalog = new \App\Domain\Catalog\ProductCatalog($productsPath, $licenseTypesPath);
$tokenService = new \TokenService(\Secrets::downloadTokenSecret());
$tokenStore = new \TokenStoreJson($tokenStorePath);
$licenseKeyGen = new \App\Infrastructure\License\LicenseKeyGenerator();
$mailer = \App\Infrastructure\Mail\MailerFactory::fromConfig($mailConfig);

$baseUrl = $appConfig['base_url'] ?? 'https://example.com';
$downloadPath = $appConfig['download_path'] ?? '/public/download.php';
$supportContact = $mailConfig['support_contact'] ?? 'support@example.com';
$subjectPrefix = $mailConfig['subject_prefix'] ?? '[Modular Web Core]';

$service = new \App\Application\Fulfillment\FulfillmentService(
    $fulfillmentRepo,
    $catalog,
    $tokenService,
    $tokenStore,
    $licenseKeyGen,
    $mailer,
    $baseUrl,
    $downloadPath,
    $engineVersionPath,
    $supportContact,
    $subjectPrefix,
);

$purchase = \App\Domain\Purchase\Purchase::create([
    'provider' => 'test',
    'provider_event_id' => 'evt_fulfill_test_' . time(),
    'customer_email' => 'test@example.com',
    'product_id' => 'starter_business_card',
    'license_type' => 'standard_license',
    'amount' => 7900,
    'currency' => 'eur',
]);

$purchaseRepo->create($purchase);

echo "Fulfillment Service test\n";
echo "========================\n";
echo "Purchase: {$purchase['purchase_id']}\n";
echo "Product: {$purchase['product_id']}\n";
echo "Mail mode: " . ($mailConfig['mode'] ?? 'pretend') . "\n\n";

$result = $service->fulfill($purchase);

echo "Status: {$result->status}\n";
echo "Success: " . ($result->success ? 'yes' : 'no') . "\n";

if ($result->downloadUrl !== null && $result->downloadUrl !== '') {
    $truncated = substr($result->downloadUrl, 0, 60) . '...' . substr($result->downloadUrl, -20);
    echo "Download URL (truncated): {$truncated}\n";
}
if ($result->licenseKey !== null) {
    echo "License key: {$result->licenseKey}\n";
}
if ($result->lastErrorCode !== null) {
    echo "Error: [{$result->lastErrorCode}] {$result->lastErrorMessage}\n";
}

$fulfillment = $fulfillmentRepo->get($purchase['purchase_id']);
if ($fulfillment !== null) {
    echo "\nFulfillment record: status={$fulfillment['status']}, attempt_count={$fulfillment['attempt_count']}\n";
}

if (!$result->success) {
    fwrite(STDERR, "FAIL: Fulfillment failed\n");
    exit(1);
}

echo "\nOK: Fulfillment completed.\n";
exit(0);
