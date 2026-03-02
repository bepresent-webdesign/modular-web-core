#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI smoke test: Delivery Foundation (Issue #7 / PR #1)
 * - Creates a Purchase
 * - Saves it
 * - Reads it
 * - Sets Fulfillment status
 * - Reads Fulfillment status
 */

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "test_delivery_foundation.php: failed to resolve project root\n");
    exit(1);
}

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

$storageRoot = $projectRoot . '/storage';
$purchasePath = $storageRoot . '/purchases/purchases.json';
$fulfillmentPath = $storageRoot . '/fulfillment/fulfillment.json';

$purchaseRepo = new \App\Infrastructure\Purchases\PurchaseRepository($purchasePath);
$fulfillmentRepo = new \App\Infrastructure\Fulfillment\FulfillmentRepository($fulfillmentPath);

echo "Delivery Foundation smoke test\n";
echo "==============================\n";

require_once $projectRoot . '/lib/Secrets.php';
require_once $projectRoot . '/lib/Privacy.php';

// 1. Create Purchase
$foundationEmail = 'test@example.com';
$purchase = \App\Domain\Purchase\Purchase::create([
    'provider' => 'stripe',
    'provider_event_id' => 'evt_test_' . time(),
    'customer_email_hash' => \Privacy::hashCustomerEmail($foundationEmail, \Secrets::customerEmailPepper()),
    'product_id' => 'modular-web-core',
    'license_type' => 'single',
    'amount' => 1999,
    'currency' => 'eur',
]);
echo "1. Purchase created: {$purchase['purchase_id']}\n";

// 2. Save
$purchaseRepo->create($purchase);
echo "2. Purchase saved\n";

// 3. Read
$read = $purchaseRepo->get($purchase['purchase_id']);
if ($read === null) {
    fwrite(STDERR, "FAIL: Could not read purchase\n");
    exit(1);
}
if ($read['product_id'] !== $purchase['product_id'] || $read['amount'] !== $purchase['amount']) {
    fwrite(STDERR, "FAIL: Read data mismatch\n");
    exit(1);
}
echo "3. Purchase read: product_id={$read['product_id']}, amount={$read['amount']}\n";

// 4. Set Fulfillment status
$fulfillmentRepo->setStatus($purchase['purchase_id'], 'fulfilled', ['smoke_test' => true]);
echo "4. Fulfillment status set: fulfilled\n";

// 5. Read Fulfillment status
$fulfillment = $fulfillmentRepo->get($purchase['purchase_id']);
if ($fulfillment === null) {
    fwrite(STDERR, "FAIL: Could not read fulfillment\n");
    exit(1);
}
if ($fulfillment['status'] !== 'fulfilled') {
    fwrite(STDERR, "FAIL: Fulfillment status mismatch: {$fulfillment['status']}\n");
    exit(1);
}
echo "5. Fulfillment read: status={$fulfillment['status']}\n";

echo "\nOK: All steps passed.\n";
exit(0);
