<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Stripe;

use App\Domain\Catalog\ProductCatalog;
use App\Domain\Exceptions\CatalogException;

/**
 * Validates a Stripe price mapping record against the product catalog.
 * Produces a normalized PurchaseIntent array for use by webhook code.
 */
final class StripePurchaseValidator
{
    public function __construct(
        private ProductCatalog $catalog
    ) {
    }

    /**
     * Validate price mapping and return normalized PurchaseIntent.
     *
     * @param array<string, mixed> $priceRecord From StripePriceMap::resolve()
     * @return array<string, mixed> PurchaseIntent with product_id, license_type, max_downloads, includes_updates, update_months, amount_minor, currency, mode
     * @throws StripePurchaseValidationException
     */
    public function validate(array $priceRecord): array
    {
        $productId = $priceRecord['product_id'] ?? null;
        if (!is_string($productId) || $productId === '') {
            throw new StripePurchaseValidationException("Price record missing or invalid product_id");
        }

        try {
            $product = $this->catalog->get($productId);
        } catch (CatalogException $e) {
            throw new StripePurchaseValidationException("Product not found in catalog: {$productId}");
        }

        $amountMinor = $priceRecord['amount_minor'] ?? null;
        if (!is_int($amountMinor) || $amountMinor < 0) {
            throw new StripePurchaseValidationException("Price record missing or invalid amount_minor");
        }

        if ($amountMinor !== ($product['price_net_eur'] ?? null)) {
            throw new StripePurchaseValidationException(
                "Amount mismatch: price record has {$amountMinor}, product expects " . ($product['price_net_eur'] ?? '?')
            );
        }

        $currency = $priceRecord['currency'] ?? null;
        if (!is_string($currency) || $currency === '') {
            throw new StripePurchaseValidationException("Price record missing or invalid currency");
        }

        $licenseTypeFromMapping = $priceRecord['license_type'] ?? null;
        $productLicenseType = $product['license_type'] ?? null;
        if ($licenseTypeFromMapping !== null && $licenseTypeFromMapping !== $productLicenseType) {
            throw new StripePurchaseValidationException(
                "License type mismatch: mapping has '{$licenseTypeFromMapping}', product has '{$productLicenseType}'"
            );
        }

        $mode = $priceRecord['mode'] ?? 'payment';
        if (!is_string($mode) || $mode === '') {
            $mode = 'payment';
        }

        return [
            'product_id' => $product['product_id'],
            'license_type' => $product['license_type'],
            'max_downloads' => $product['max_downloads'],
            'includes_updates' => $product['includes_updates'],
            'update_months' => $product['update_months'],
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'mode' => $mode,
        ];
    }
}
