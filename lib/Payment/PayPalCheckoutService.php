<?php

declare(strict_types=1);

/**
 * PayPal Checkout Orders API v2.
 * createOrder, captureOrder, verifyWebhook.
 */
final class PayPalCheckoutService
{
    private string $baseUrl;
    private string $clientId;
    private string $secret;
    private string $webhookId;
    private string $returnUrl;
    private string $cancelUrl;

    public function __construct(
        string $clientId,
        string $secret,
        string $webhookId,
        bool $sandbox,
        string $returnUrl,
        string $cancelUrl,
    ) {
        $this->clientId = $clientId;
        $this->secret = $secret;
        $this->webhookId = $webhookId;
        $this->returnUrl = $returnUrl;
        $this->cancelUrl = $cancelUrl;
        $this->baseUrl = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    /**
     * Create order and return approval URL.
     * productId is resolved via catalog; custom_id stores product_id|license_type for webhook.
     */
    public function createOrder(string $productId, \App\Domain\Catalog\ProductCatalog $catalog): string
    {
        $product = $catalog->get($productId);
        $licenseType = $product['license_type'] ?? '';
        $amountMinor = (int) ($product['price_net_eur'] ?? 0);
        $currency = 'eur';
        $customId = $productId . '|' . $licenseType;
        $value = number_format($amountMinor / 100, 2, '.', '');
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => strtoupper($currency),
                        'value' => $value,
                    ],
                    'custom_id' => $customId,
                ],
            ],
            'application_context' => [
                'return_url' => $this->returnUrl,
                'cancel_url' => $this->cancelUrl,
            ],
        ];

        $token = $this->getAccessToken();
        $resp = $this->request('POST', $this->baseUrl . '/v2/checkout/orders', $token, $payload);

        $links = $resp['links'] ?? [];
        foreach ($links as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $url = $link['href'] ?? '';
                if ($url !== '') {
                    return $url;
                }
            }
        }
        throw new \RuntimeException('No approval URL in PayPal response');
    }

    /**
     * Get order details (for custom_id from purchase_units).
     *
     * @return array<string, mixed>
     */
    public function getOrder(string $orderId): array
    {
        $token = $this->getAccessToken();
        return $this->request(
            'GET',
            $this->baseUrl . '/v2/checkout/orders/' . urlencode($orderId),
            $token,
            null,
        );
    }

    /**
     * Capture order. Returns capture details.
     *
     * @return array<string, mixed>
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();
        $resp = $this->request(
            'POST',
            $this->baseUrl . '/v2/checkout/orders/' . urlencode($orderId) . '/capture',
            $token,
            (object) [],
            ['Prefer' => 'return=representation'],
        );
        return is_array($resp) ? $resp : [];
    }

    /**
     * Verify webhook signature via PayPal official endpoint.
     * Fail closed: return false on any error or missing header.
     *
     * @see https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature_post
     *      POST /v1/notifications/verify-webhook-signature
     *
     * @param array<string, string> $headers PAYPAL-TRANSMISSION-ID, PAYPAL-TRANSMISSION-SIG, PAYPAL-TRANSMISSION-TIME, PAYPAL-AUTH-ALGO, PAYPAL-CERT-URL
     */
    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $transmissionId = $headers['PAYPAL-TRANSMISSION-ID'] ?? $headers['paypal-transmission-id'] ?? '';
        $transmissionSig = $headers['PAYPAL-TRANSMISSION-SIG'] ?? $headers['paypal-transmission-sig'] ?? '';
        $transmissionTime = $headers['PAYPAL-TRANSMISSION-TIME'] ?? $headers['paypal-transmission-time'] ?? '';
        $authAlgo = $headers['PAYPAL-AUTH-ALGO'] ?? $headers['paypal-auth-algo'] ?? '';
        $certUrl = $headers['PAYPAL-CERT-URL'] ?? $headers['paypal-cert-url'] ?? '';

        if ($transmissionId === '' || $transmissionSig === '' || $transmissionTime === ''
            || $authAlgo === '' || $certUrl === '' || $this->webhookId === '') {
            return false;
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return false;
        }

        $body = [
            'auth_algo' => $authAlgo,
            'cert_url' => $certUrl,
            'transmission_id' => $transmissionId,
            'transmission_sig' => $transmissionSig,
            'transmission_time' => $transmissionTime,
            'webhook_id' => $this->webhookId,
            'webhook_event' => $event,
        ];

        try {
            $resp = $this->request(
                'POST',
                $this->baseUrl . '/v1/notifications/verify-webhook-signature',
                $this->getAccessToken(),
                $body,
            );
        } catch (Throwable) {
            return false;
        }

        return ($resp['verification_status'] ?? '') === 'SUCCESS';
    }

    private function getAccessToken(): string
    {
        $auth = base64_encode($this->clientId . ':' . $this->secret);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Basic {$auth}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                'content' => 'grant_type=client_credentials',
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($this->baseUrl . '/v1/oauth2/token', false, $ctx);
        if ($response === false) {
            throw new \RuntimeException('PayPal OAuth failed');
        }
        $data = json_decode($response, true);
        $token = $data['access_token'] ?? '';
        if ($token === '') {
            throw new \RuntimeException('PayPal OAuth: no access token');
        }
        return $token;
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    private function request(string $method, string $url, string $token, mixed $body, array $extraHeaders = []): array
    {
        $headers = [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json',
        ];
        foreach ($extraHeaders as $k => $v) {
            $headers[] = "{$k}: {$v}";
        }

        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
            ],
        ];
        if ($method === 'POST' && $body !== (object) [] && $body !== []) {
            $opts['http']['content'] = json_encode($body);
        }

        $response = @file_get_contents($url, false, stream_context_create($opts));
        if ($response === false) {
            throw new \RuntimeException('PayPal API request failed');
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }
}
