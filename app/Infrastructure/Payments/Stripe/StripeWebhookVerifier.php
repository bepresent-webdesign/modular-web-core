<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Stripe;

/**
 * Verifies Stripe webhook signatures without requiring Stripe SDK.
 * Uses raw body, Stripe-Signature header, and endpoint secret.
 * Enforces timestamp tolerance to prevent replay attacks.
 */
final class StripeWebhookVerifier
{
    /** Default tolerance: 5 minutes (matches Stripe PHP SDK). */
    private const DEFAULT_TOLERANCE = 300;

    public function __construct(
        private string $secret,
        private int $toleranceSeconds = self::DEFAULT_TOLERANCE
    ) {
    }

    /**
     * Verify signature and return decoded event payload.
     *
     * @return array<string, mixed> Decoded event array
     * @throws StripeWebhookVerificationException on signature failure or timestamp out of tolerance
     */
    public function verify(string $rawBody, string $signatureHeader): array
    {
        if ($rawBody === '') {
            throw new StripeWebhookVerificationException('Empty request body');
        }

        $header = trim($signatureHeader);
        if ($header === '') {
            throw new StripeWebhookVerificationException('Missing or empty Stripe-Signature header');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $pos = strpos($part, '=');
            if ($pos === false) {
                continue;
            }
            $prefix = trim(substr($part, 0, $pos));
            $value = trim(substr($part, $pos + 1));
            if ($prefix === 't') {
                $timestamp = $value;
            } elseif ($prefix === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $timestamp === '') {
            throw new StripeWebhookVerificationException('Missing timestamp in Stripe-Signature');
        }

        if (!ctype_digit($timestamp)) {
            throw new StripeWebhookVerificationException('Invalid timestamp in Stripe-Signature');
        }

        $timestampInt = (int) $timestamp;
        $now = time();
        if (abs($now - $timestampInt) > $this->toleranceSeconds) {
            throw new StripeWebhookVerificationException('Webhook timestamp outside tolerance');
        }

        if ($signatures === []) {
            throw new StripeWebhookVerificationException('Missing v1 signature in Stripe-Signature');
        }

        // Stripe spec: signed_payload = "{timestamp}.{payload}" (not payload.timestamp)
        $signedPayload = $timestamp . '.' . $rawBody;
        $expectedSig = hash_hmac('sha256', $signedPayload, $this->secret, false);

        $valid = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSig, $sig)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new StripeWebhookVerificationException('Invalid signature');
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            throw new StripeWebhookVerificationException('Invalid JSON payload');
        }

        return $decoded;
    }
}
