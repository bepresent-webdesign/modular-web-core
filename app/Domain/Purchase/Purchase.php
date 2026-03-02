<?php

declare(strict_types=1);

namespace App\Domain\Purchase;

use RuntimeException;

/**
 * Purchase value object / array-shape for the Delivery Layer.
 * Provider-agnostic: stores provider + provider_event_id for idempotency.
 *
 * Shape:
 * - purchase_id: string (internal, stable)
 * - provider: string (e.g. "stripe")
 * - provider_event_id: string
 * - customer_email_hash: ?string (sha256 of normalized email + pepper, DSGVO)
 * - product_id: string
 * - license_type: string
 * - amount: int (minor units, e.g. cents)
 * - currency: string
 * - created_at: string (ISO 8601)
 * - metadata: array (optional)
 */
final class Purchase
{
    public static function generateId(): string
    {
        return 'pur_' . time() . '_' . bin2hex(random_bytes(6));
    }

    /**
     * Build a normalized Purchase record from input data.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function create(array $input): array
    {
        $purchaseId = $input['purchase_id'] ?? self::generateId();
        if (!is_string($purchaseId) || $purchaseId === '') {
            throw new RuntimeException('purchase_id must be a non-empty string', 1);
        }

        $provider = $input['provider'] ?? null;
        if (!is_string($provider) || $provider === '') {
            throw new RuntimeException('provider must be a non-empty string', 2);
        }

        $providerEventId = $input['provider_event_id'] ?? null;
        if (!is_string($providerEventId) || $providerEventId === '') {
            throw new RuntimeException('provider_event_id must be a non-empty string', 3);
        }

        $productId = $input['product_id'] ?? null;
        if (!is_string($productId) || $productId === '') {
            throw new RuntimeException('product_id must be a non-empty string', 4);
        }

        $licenseType = $input['license_type'] ?? null;
        if (!is_string($licenseType) || $licenseType === '') {
            throw new RuntimeException('license_type must be a non-empty string', 5);
        }

        $amount = $input['amount'] ?? null;
        if (!is_int($amount) || $amount < 0) {
            throw new RuntimeException('amount must be a non-negative integer (minor units)', 6);
        }

        $currency = $input['currency'] ?? null;
        if (!is_string($currency) || $currency === '') {
            throw new RuntimeException('currency must be a non-empty string', 7);
        }

        $customerEmailHash = $input['customer_email_hash'] ?? null;
        if ($customerEmailHash !== null && !is_string($customerEmailHash)) {
            throw new RuntimeException('customer_email_hash must be string or null', 8);
        }

        return [
            'purchase_id' => $purchaseId,
            'provider' => $provider,
            'provider_event_id' => $providerEventId,
            'customer_email_hash' => $customerEmailHash,
            'product_id' => $productId,
            'license_type' => $licenseType,
            'amount' => $amount,
            'currency' => $currency,
            'created_at' => $input['created_at'] ?? date('c'),
            'metadata' => is_array($input['metadata'] ?? null) ? $input['metadata'] : [],
        ];
    }
}
