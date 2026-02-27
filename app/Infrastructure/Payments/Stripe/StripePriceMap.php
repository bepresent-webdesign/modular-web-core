<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Stripe;

/**
 * Maps Stripe price IDs to canonical purchase intents.
 * Loads config/prices.stripe.php.
 */
final class StripePriceMap
{
    /** @var array<string, array<string, mixed>> */
    private array $prices;

    public function __construct(string $configPath)
    {
        $this->prices = $this->loadPrices($configPath);
    }

    /**
     * Resolve a Stripe price ID to its mapped record.
     *
     * @return array<string, mixed> Mapped record with product_id, mode, currency, amount_minor, metadata_required
     * @throws StripePriceMapException
     */
    public function resolve(string $priceId): array
    {
        if (!isset($this->prices[$priceId])) {
            throw new StripePriceMapException("Unknown Stripe price ID: {$priceId}");
        }

        return $this->prices[$priceId];
    }

    /**
     * Find Stripe price ID by product_id. Returns first matching price.
     *
     * @return array{price_id: string, record: array<string, mixed>}|null
     */
    public function findByProductId(string $productId): ?array
    {
        foreach ($this->prices as $priceId => $record) {
            if (($record['product_id'] ?? '') === $productId) {
                return ['price_id' => $priceId, 'record' => $record];
            }
        }
        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadPrices(string $path): array
    {
        if (!is_file($path)) {
            throw new StripePriceMapException("Stripe prices config not found: {$path}");
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new StripePriceMapException("Stripe prices config must return an array");
        }

        return $data;
    }
}
