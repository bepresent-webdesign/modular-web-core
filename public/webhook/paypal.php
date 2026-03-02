<?php

declare(strict_types=1);

/**
 * PayPal webhook endpoint.
 * POST only. Verifies signature, processes PAYMENT.CAPTURE.COMPLETED.
 * Idempotency via PurchaseRepository.getByProviderEvent.
 * Returns 200 on success/duplicate; 400 on signature failure; 500 on setup errors.
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
require_once CORE_ROOT . '/lib/Download/TokenService.php';
require_once CORE_ROOT . '/lib/Download/TokenStoreJson.php';
require_once CORE_ROOT . '/lib/Payment/PayPalCheckoutService.php';

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

$config = require CORE_ROOT . '/config/paypal.php';
if (!is_array($config)) {
    respond500();
}

$clientId = $config['client_id'] ?? '';
$secret = $config['secret'] ?? '';
$webhookId = $config['webhook_id'] ?? '';
$sandbox = (bool) ($config['sandbox'] ?? true);

if ($clientId === '' || $secret === '' || $webhookId === '') {
    respond500();
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

$headers = [];
foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_') && is_string($v)) {
        $name = str_replace('_', '-', substr($k, 5));
        $headers[$name] = $v;
    }
}

if (!$service->verifyWebhook($headers, $rawBody)) {
    respond400();
}

$event = json_decode($rawBody, true);
if (!is_array($event)) {
    respond400();
}

$eventType = $event['event_type'] ?? '';
if ($eventType !== 'PAYMENT.CAPTURE.COMPLETED') {
    respond200();
}

$eventId = $event['id'] ?? '';
if ($eventId === '') {
    respond400();
}

$storageRoot = CORE_ROOT . '/storage';
$eventStore = new \App\Infrastructure\Webhooks\WebhookEventStore($storageRoot . '/webhooks/processed-events.json');
if ($eventStore->has($eventId)) {
    respond200();
}

$resource = $event['resource'] ?? null;
if (!is_array($resource)) {
    $eventStore->markProcessed($eventId, $eventType);
    respond200();
}

$providerEventId = $eventId;
$orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
if (!is_string($orderId) || $orderId === '') {
    $links = $resource['links'] ?? [];
    foreach ($links as $link) {
        if (($link['rel'] ?? '') === 'up') {
            $href = $link['href'] ?? '';
            if (preg_match('#/orders/([A-Z0-9]+)$#i', $href, $m)) {
                $orderId = $m[1];
                break;
            }
        }
    }
}

$amountObj = $resource['amount'] ?? [];
$amount = isset($amountObj['value']) ? (int) round((float) $amountObj['value'] * 100) : 0;
$currency = $amountObj['currency_code'] ?? 'eur';
$currency = is_string($currency) ? strtolower($currency) : 'eur';

$customerEmail = null;
$payer = $resource['payer'] ?? $event['resource']['payer'] ?? null;
if (is_array($payer)) {
    $email = $payer['email_address'] ?? null;
    $customerEmail = is_string($email) ? $email : null;
}

$productId = null;
$licenseType = null;

if (is_string($orderId) && $orderId !== '') {
    try {
        $orderData = $service->getOrder($orderId);
        $units = $orderData['purchase_units'] ?? [];
        $customId = $units[0]['custom_id'] ?? null;
        if (is_string($customId) && str_contains($customId, '|')) {
            $parts = explode('|', $customId, 2);
            $productId = trim($parts[0] ?? '');
            $licenseType = trim($parts[1] ?? '');
        }
    } catch (Throwable) {
        $productId = null;
        $licenseType = null;
    }
}

$purchaseRepo = new \App\Infrastructure\Purchases\PurchaseRepository($storageRoot . '/purchases/purchases.json');
$fulfillmentRepo = new \App\Infrastructure\Fulfillment\FulfillmentRepository($storageRoot . '/fulfillment/fulfillment.json');
$existingPurchase = $purchaseRepo->getByProviderEvent('paypal', $providerEventId);

if ($productId === null || $productId === '' || $licenseType === null || $licenseType === '') {
    if ($existingPurchase !== null) {
        $purchaseId = $existingPurchase['purchase_id'];
    } else {
        $purchase = \App\Domain\Purchase\Purchase::create([
            'provider' => 'paypal',
            'provider_event_id' => $providerEventId,
            'customer_email_hash' => $customerEmailHash,
            'product_id' => '_invalid_',
            'license_type' => '_unknown_',
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => [
                'provider_ref' => $resource['id'] ?? null,
                'order_id' => $orderId,
                'invalid_reason' => 'custom_id not found in order',
            ],
        ]);
        $purchaseRepo->create($purchase);
        $purchaseId = $purchase['purchase_id'];
    }
    $fulfillmentRepo->setStatus($purchaseId, 'failed', [
        'last_error_code' => 'invalid',
        'last_error_message' => 'product_id/license_type not in order custom_id',
    ]);
    $eventStore->markProcessed($eventId, $eventType);
    respond200();
}

$purchase = $existingPurchase;
if ($purchase === null) {
    $purchase = \App\Domain\Purchase\Purchase::create([
        'provider' => 'paypal',
        'provider_event_id' => $providerEventId,
        'customer_email_hash' => $customerEmailHash,
        'product_id' => $productId,
        'license_type' => $licenseType,
        'amount' => $amount,
        'currency' => $currency,
        'metadata' => array_filter([
            'provider_ref' => $resource['id'] ?? null,
            'order_id' => $orderId,
        ]),
    ]);
    $purchaseRepo->create($purchase);
}

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
