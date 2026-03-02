<?php

declare(strict_types=1);

/**
 * Stripe webhook endpoint (PR #3: provider-agnostic Purchase + FulfillmentService).
 * POST only. Verifies signature, enforces idempotency, processes checkout.session.completed.
 * Returns 200 on success, duplicate, or fulfillment failure (no retry storm).
 * Returns 400 on signature failure; 500 only on critical setup errors.
 */

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

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

require_once CORE_ROOT . '/lib/Secrets.php';
require_once CORE_ROOT . '/lib/Privacy.php';
require_once CORE_ROOT . '/lib/Download/TokenService.php';
require_once CORE_ROOT . '/lib/Download/TokenStoreJson.php';

$rawBody = (string) file_get_contents('php://input');

function respond400(): never
{
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Bad request']);
    exit;
}

function respond500(): never
{
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
    exit;
}

function respond200(): never
{
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['received' => true]);
    exit;
}

$signatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
if ($signatureHeader === '') {
    respond400();
}

$webhookSecret = null;
try {
    $webhookSecret = \Secrets::stripeWebhookSecret();
} catch (Throwable $e) {
    respond500();
}

$verifier = new \App\Infrastructure\Payments\Stripe\StripeWebhookVerifier($webhookSecret);
try {
    $event = $verifier->verify($rawBody, $signatureHeader);
} catch (\App\Infrastructure\Payments\Stripe\StripeWebhookVerificationException $e) {
    respond400();
}

$eventId = $event['id'] ?? '';
$eventType = $event['type'] ?? '';
if ($eventId === '' || $eventType === '') {
    respond400();
}

$storageRoot = CORE_ROOT . '/storage';
$eventStore = new \App\Infrastructure\Webhooks\WebhookEventStore($storageRoot . '/webhooks/processed-events.json');

if ($eventStore->has($eventId)) {
    respond200();
}

if ($eventType !== 'checkout.session.completed') {
    $eventStore->markProcessed($eventId, $eventType);
    respond200();
}

$session = $event['data']['object'] ?? null;
if (!is_array($session)) {
    $eventStore->markProcessed($eventId, $eventType);
    respond200();
}

$paymentStatus = $session['payment_status'] ?? '';
if ($paymentStatus !== 'paid') {
    $eventStore->markProcessed($eventId, $eventType);
    respond200();
}

// --- Extract session data (provider-agnostic shape) ---
$providerEventId = $eventId;
$providerRef = $session['id'] ?? null;
$providerRef = is_string($providerRef) ? $providerRef : null;

$customerDetails = $session['customer_details'] ?? [];
$customerEmail = is_array($customerDetails) && isset($customerDetails['email'])
    ? $customerDetails['email']
    : ($session['customer_email'] ?? null);
$customerEmail = is_string($customerEmail) ? $customerEmail : null;

$amountTotal = $session['amount_total'] ?? null;
$amount = is_int($amountTotal) && $amountTotal >= 0 ? $amountTotal : 0;
$currency = $session['currency'] ?? 'eur';
$currency = is_string($currency) ? strtolower($currency) : 'eur';

$metadata = $session['metadata'] ?? [];
$metadata = is_array($metadata) ? $metadata : [];

// --- Resolve product_id and license_type ---
$productId = isset($metadata['product_id']) && is_string($metadata['product_id'])
    ? trim($metadata['product_id'])
    : null;
$licenseType = isset($metadata['license_type']) && is_string($metadata['license_type'])
    ? trim($metadata['license_type'])
    : null;

if ($productId === '' || $licenseType === '') {
    $productId = null;
    $licenseType = null;
}

$priceId = $metadata['price_id'] ?? null;
if (!is_string($priceId) || $priceId === '') {
    $lineItems = $session['line_items'] ?? null;
    if (is_array($lineItems) && isset($lineItems['data'][0]['price']['id'])) {
        $priceId = $lineItems['data'][0]['price']['id'];
    }
}
$priceId = is_string($priceId) ? $priceId : null;

if ($productId === null && $priceId !== null) {
    try {
        $pricesPath = CORE_ROOT . '/config/prices.stripe.php';
        $priceMap = new \App\Infrastructure\Payments\Stripe\StripePriceMap($pricesPath);
        $priceRecord = $priceMap->resolve($priceId);

        $productsPath = CORE_ROOT . '/config/products.php';
        $licenseTypesPath = CORE_ROOT . '/config/license-types.php';
        $catalog = new \App\Domain\Catalog\ProductCatalog($productsPath, $licenseTypesPath);
        $validator = new \App\Infrastructure\Payments\Stripe\StripePurchaseValidator($catalog);
        $purchaseIntent = $validator->validate($priceRecord);

        $productId = $purchaseIntent['product_id'] ?? null;
        $licenseType = $purchaseIntent['license_type'] ?? null;
    } catch (Throwable) {
        $productId = null;
        $licenseType = null;
    }
}

$purchaseRepo = new \App\Infrastructure\Purchases\PurchaseRepository($storageRoot . '/purchases/purchases.json');
$fulfillmentRepo = new \App\Infrastructure\Fulfillment\FulfillmentRepository($storageRoot . '/fulfillment/fulfillment.json');

$existingPurchase = $purchaseRepo->getByProviderEvent('stripe', $providerEventId);

if ($productId === null || $productId === '' || $licenseType === null || $licenseType === '') {
    if ($existingPurchase !== null) {
        $purchaseId = $existingPurchase['purchase_id'];
    } else {
        $purchase = \App\Domain\Purchase\Purchase::create([
            'provider' => 'stripe',
            'provider_event_id' => $providerEventId,
            'customer_email' => $customerEmail,
            'product_id' => '_invalid_',
            'license_type' => '_unknown_',
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => [
                'provider_ref' => $providerRef,
                'price_id' => $priceId,
                'invalid_reason' => 'product_id and license_type could not be resolved',
            ],
        ]);
        $purchaseRepo->create($purchase);
        $purchaseId = $purchase['purchase_id'];
    }

    $fulfillmentRepo->setStatus($purchaseId, 'failed', [
        'last_error_code' => 'invalid',
        'last_error_message' => 'product_id/license_type not in metadata or price map',
    ]);
    $eventStore->markProcessed($eventId, $eventType);
    respond200();
}

$purchase = $existingPurchase;
if ($purchase === null) {
    $purchase = \App\Domain\Purchase\Purchase::create([
        'provider' => 'stripe',
        'provider_event_id' => $providerEventId,
        'customer_email' => $customerEmail,
        'product_id' => $productId,
        'license_type' => $licenseType,
        'amount' => $amount,
        'currency' => $currency,
        'metadata' => array_filter([
            'provider_ref' => $providerRef,
            'price_id' => $priceId,
        ]),
    ]);
    $purchaseRepo->create($purchase);
}

$appConfig = require CORE_ROOT . '/config/app.php';
$mailConfig = require CORE_ROOT . '/config/mail.php';
$productsPath = CORE_ROOT . '/config/products.php';
$licenseTypesPath = CORE_ROOT . '/config/license-types.php';

$fulfillmentService = new \App\Application\Fulfillment\FulfillmentService(
    $fulfillmentRepo,
    new \App\Domain\Catalog\ProductCatalog($productsPath, $licenseTypesPath),
    new \TokenService(\Secrets::downloadTokenSecret()),
    new \TokenStoreJson(CORE_ROOT . '/data/download_tokens.json'),
    new \App\Infrastructure\License\LicenseKeyGenerator(),
    \App\Infrastructure\Mail\MailerFactory::fromConfig($mailConfig),
    $appConfig['base_url'] ?? 'https://example.com',
    $appConfig['download_path'] ?? '/public/download.php',
    CORE_ROOT . '/engine-version.json',
    $mailConfig['support_contact'] ?? '',
    $mailConfig['subject_prefix'] ?? '[Modular Web Core]',
);

$fulfillmentService->fulfill($purchase, $customerEmail);

$eventStore->markProcessed($eventId, $eventType);
respond200();
