<?php

declare(strict_types=1);

namespace App\Application\Fulfillment;

use App\Domain\Catalog\ProductCatalog;
use App\Infrastructure\Fulfillment\FulfillmentRepository;
use App\Infrastructure\License\LicenseKeyGenerator;
use App\Infrastructure\Mail\MailerInterface;
use RuntimeException;
use TokenService;
use TokenStoreJson;

/**
 * Synchronous, provider-agnostic fulfillment service.
 * Creates license key, download token, stores fulfillment data, optionally sends email.
 */
final class FulfillmentService
{
    public function __construct(
        private FulfillmentRepository $fulfillmentRepo,
        private ProductCatalog $catalog,
        private TokenService $tokenService,
        private TokenStoreJson $tokenStore,
        private LicenseKeyGenerator $licenseKeyGenerator,
        private MailerInterface $mailer,
        private string $baseUrl,
        private string $downloadPath,
        private string $engineVersionPath,
        private string $supportContact = '',
        private string $subjectPrefix = '[Modular Web Core]',
    ) {
    }

    /**
     * Fulfill a purchase: create license, token, optionally send email.
     * Idempotent: if already fulfilled, returns success without side effects.
     *
     * @param array<string, mixed> $purchase Purchase record (from Purchase::create)
     * @param string|null $customerEmail Email for delivery (not persisted, DSGVO)
     */
    public function fulfill(array $purchase, ?string $customerEmail = null): FulfillmentResult
    {
        $purchaseId = $purchase['purchase_id'] ?? '';
        if ($purchaseId === '') {
            return FulfillmentResult::failed('invalid_purchase', 'Missing purchase_id');
        }

        $existing = $this->fulfillmentRepo->get($purchaseId);
        if ($existing !== null && ($existing['status'] ?? '') === 'fulfilled') {
            return FulfillmentResult::idempotent('', '');
        }

        $this->fulfillmentRepo->setStatus($purchaseId, 'processing');

        try {
            $maxDownloads = $this->getMaxDownloads($purchase['product_id'] ?? '');
            $productName = $this->getProductName($purchase['product_id'] ?? '');
            $licenseKey = $this->licenseKeyGenerator->generate();
            $licenseKeyHash = hash('sha256', $licenseKey);

            $engineVersion = $this->resolveEngineVersion();
            $tokenId = 'tok_' . bin2hex(random_bytes(12));
            $tokenExpiry = time() + 86400 * 7;
            $tokenClaims = [
                'token_id' => $tokenId,
                'exp' => $tokenExpiry,
                'file_key' => 'engine_zip',
                'engine_version' => $engineVersion,
            ];
            $tokenString = $this->tokenService->makeToken($tokenClaims);

            $tokenRecord = [
                'token_id' => $tokenId,
                'status' => 'active',
                'created_at' => date('c'),
                'exp' => $tokenExpiry,
                'file_key' => 'engine_zip',
                'engine_version' => $engineVersion,
                'max_downloads' => $maxDownloads,
                'download_count' => 0,
                'metadata' => [
                    'order_ref' => $purchaseId,
                    'customer_ref' => null,
                    'payment_provider' => null,
                ],
            ];
            $this->tokenStore->put($tokenRecord);

            $downloadUrl = rtrim($this->baseUrl, '/') . $this->downloadPath . '?token=' . urlencode($tokenString);

            if (is_string($customerEmail) && $customerEmail !== '') {
                $this->sendDeliveryEmail($customerEmail, $purchase, $productName, $licenseKey, $downloadUrl, $maxDownloads);
            }

            $this->fulfillmentRepo->setStatus($purchaseId, 'fulfilled', [
                'delivered_at' => date('c'),
                'token_id' => $tokenId,
                'license_key_hash' => $licenseKeyHash,
            ]);

            return FulfillmentResult::success($downloadUrl, $licenseKey);
        } catch (Throwable $e) {
            $this->fulfillmentRepo->setStatus($purchaseId, 'failed', [
                'last_error_code' => 'fulfillment_error',
                'last_error_message' => substr($e->getMessage(), 0, 200),
            ]);
            return FulfillmentResult::failed('fulfillment_error', $e->getMessage());
        }
    }

    private function getMaxDownloads(string $productId): int
    {
        try {
            $product = $this->catalog->get($productId);
            $max = $product['max_downloads'] ?? 5;
            return is_int($max) && $max > 0 ? $max : 5;
        } catch (Throwable) {
            return 5;
        }
    }

    private function getProductName(string $productId): string
    {
        try {
            $product = $this->catalog->get($productId);
            return $product['name'] ?? $productId;
        } catch (Throwable) {
            return $productId;
        }
    }

    private function resolveEngineVersion(): string
    {
        if (!is_file($this->engineVersionPath)) {
            return '0.3.0';
        }
        $data = json_decode((string) file_get_contents($this->engineVersionPath), true);
        if (!is_array($data)) {
            return '0.3.0';
        }
        $v = $data['version'] ?? $data['engine_version'] ?? '0.3.0';
        return is_string($v) ? $v : '0.3.0';
    }

    private function sendDeliveryEmail(
        string $to,
        array $purchase,
        string $productName,
        string $licenseKey,
        string $downloadUrl,
        int $maxDownloads,
    ): void {
        $licenseType = $purchase['license_type'] ?? 'standard';
        $expiryDays = 7;
        $body = $this->renderDeliveryTemplate($productName, $licenseType, $licenseKey, $downloadUrl, $maxDownloads, $expiryDays);
        $subject = $this->resolveSubject($productName);
        $this->mailer->send($to, $subject, $body);
    }

    private function renderDeliveryTemplate(
        string $productName,
        string $licenseType,
        string $licenseKey,
        string $downloadUrl,
        int $maxDownloads,
        int $expiryDays,
    ): string {
        ob_start();
        $supportContact = $this->supportContact;
        require __DIR__ . '/../../Infrastructure/Mail/templates/license_delivery.txt.php';
        return (string) ob_get_clean();
    }

    private function resolveSubject(string $productName): string
    {
        return trim($this->subjectPrefix . ' ' . $productName . ' – Lizenz & Download');
    }
}
