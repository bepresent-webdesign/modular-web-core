<?php

declare(strict_types=1);

namespace App\Application\Checkout;

use App\Domain\Catalog\ProductCatalog;
use App\Infrastructure\Payments\Stripe\StripePriceMap;
use App\Infrastructure\Payments\Stripe\StripePriceMapException;
use RuntimeException;

/**
 * Creates Stripe Checkout Sessions for one-time purchases.
 * Always sets metadata.product_id and metadata.license_type for reliable webhooks.
 */
final class StripeCheckoutService
{
    public function __construct(
        private ProductCatalog $catalog,
        private StripePriceMap $priceMap,
        private string $secretKey,
        private string $successUrl,
        private string $cancelUrl,
    ) {
    }

    /**
     * Create a Checkout Session and return the redirect URL.
     *
     * @throws StripeCheckoutException on validation or API failure
     */
    public function createSession(string $productId, string $licenseType): string
    {
        $productId = trim($productId);
        $licenseType = trim($licenseType);

        if ($productId === '' || $licenseType === '') {
            throw new StripeCheckoutException('product_id and license_type are required', 'invalid_input');
        }

        try {
            $product = $this->catalog->get($productId);
        } catch (\App\Domain\Exceptions\CatalogException) {
            throw new StripeCheckoutException('Product not found', 'invalid_product');
        }

        $productLicenseType = $product['license_type'] ?? '';
        if ($productLicenseType !== $licenseType) {
            throw new StripeCheckoutException('License type does not match product', 'invalid_license_type');
        }

        $found = $this->priceMap->findByProductId($productId);
        if ($found === null) {
            throw new StripeCheckoutException('No Stripe price mapping for selection', 'no_price_mapping');
        }

        $priceId = $found['price_id'];

        $metadata = [
            'product_id' => $productId,
            'license_type' => $licenseType,
            'purchase_ref' => 'purref_' . bin2hex(random_bytes(8)),
        ];

        $params = [
            'mode' => 'payment',
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
            'metadata[product_id]' => $metadata['product_id'],
            'metadata[license_type]' => $metadata['license_type'],
            'metadata[purchase_ref]' => $metadata['purchase_ref'],
        ];

        $response = $this->callStripeApi($params);

        $url = $response['url'] ?? null;
        if (!is_string($url) || $url === '') {
            $err = $response['error']['message'] ?? 'Unknown Stripe API error';
            throw new StripeCheckoutException($err, 'stripe_api');
        }

        return $url;
    }

    /**
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function callStripeApi(array $params): array
    {
        $body = http_build_query($params);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$this->secretKey}\r\n" .
                    "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents('https://api.stripe.com/v1/checkout/sessions', false, $ctx);

        if ($response === false) {
            throw new StripeCheckoutException('Stripe API request failed', 'stripe_api');
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new StripeCheckoutException('Invalid Stripe API response', 'stripe_api');
        }

        if (isset($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? 'Stripe API error';
            throw new StripeCheckoutException($msg, 'stripe_api');
        }

        return $decoded;
    }
}
