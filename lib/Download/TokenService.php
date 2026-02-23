<?php

declare(strict_types=1);

/**
 * HMAC-signed token service (JWT-light).
 * Token format: base64url(payload_json) . "." . base64url(hmac_sha256(payload_b64, secret))
 * Signature is over the payload_b64 string for stability across encodings.
 */
final class TokenService
{
    private const REQUIRED_CLAIMS = ['token_id', 'exp', 'file_key', 'engine_version'];

    private string $secret;
    private ?string $issuer;

    public function __construct(string $secret, ?string $issuer = null)
    {
        $this->secret = $secret;
        $this->issuer = $issuer;
    }

    /**
     * Create a signed token from claims.
     *
     * @param array<string, mixed> $claims token_id, exp, file_key, engine_version required
     * @throws RuntimeException on validation failure
     */
    public function makeToken(array $claims): string
    {
        $this->validateClaimsForMake($claims);
        $payload = $claims;

        if (!isset($payload['iat'])) {
            $payload['iat'] = time();
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode payload JSON', 10);
        }

        $payloadB64 = self::base64urlEncode($json);
        $sigB64 = $this->sign($payloadB64);

        return $payloadB64 . '.' . $sigB64;
    }

    /**
     * Verify token and return payload array.
     *
     * @return array<string, mixed> decoded claims
     * @throws RuntimeException on invalid or expired token
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token, 3);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \RuntimeException('Invalid token format', 20);
        }

        [$payloadB64, $sigB64] = $parts;

        try {
            $payloadRaw = self::base64urlDecode($payloadB64);
            $sigRaw = self::base64urlDecode($sigB64);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Invalid base64url in token', 21);
        }

        // Reject non-canonical base64url (ignored-bit tampering)
        if (self::base64urlEncode($payloadRaw) !== $payloadB64) {
            throw new \RuntimeException('Non-canonical base64url in payload', 21);
        }
        if (self::base64urlEncode($sigRaw) !== $sigB64) {
            throw new \RuntimeException('Non-canonical base64url in signature', 21);
        }

        $expectedRaw = self::base64urlDecode($this->sign($payloadB64));
        if (!hash_equals($expectedRaw, $sigRaw)) {
            throw new \RuntimeException('Invalid token signature', 22);
        }

        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid payload JSON', 23);
        }

        $exp = $payload['exp'] ?? null;
        if (!is_int($exp) || time() >= $exp) {
            throw new \RuntimeException('Token expired', 24);
        }

        if ($this->issuer !== null) {
            $iss = $payload['iss'] ?? null;
            if (!is_string($iss) || $iss !== $this->issuer) {
                throw new \RuntimeException('Invalid issuer', 25);
            }
        }

        return $payload;
    }

    private function sign(string $payloadB64): string
    {
        $raw = hash_hmac('sha256', $payloadB64, $this->secret, true);
        return self::base64urlEncode($raw);
    }

    private function validateClaimsForMake(array $claims): void
    {
        foreach (self::REQUIRED_CLAIMS as $key) {
            if (!array_key_exists($key, $claims)) {
                throw new \RuntimeException("Missing required claim: {$key}", 11);
            }
        }

        if (!is_string($claims['token_id'] ?? null)) {
            throw new \RuntimeException('token_id must be string', 12);
        }
        if (!is_int($claims['exp'] ?? null)) {
            throw new \RuntimeException('exp must be int', 13);
        }
        if (!is_string($claims['file_key'] ?? null)) {
            throw new \RuntimeException('file_key must be string', 14);
        }
        if (!is_string($claims['engine_version'] ?? null)) {
            throw new \RuntimeException('engine_version must be string', 15);
        }

        $exp = (int) $claims['exp'];
        if ($exp <= time()) {
            throw new \RuntimeException('exp must be in the future', 16);
        }
    }

    /** Base64url encode (no padding, URL-safe charset). */
    public static function base64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** Base64url decode. @throws RuntimeException on invalid input */
    public static function base64urlDecode(string $b64): string
    {
        $padded = str_pad(strtr($b64, '-_', '+/'), strlen($b64) + (4 - strlen($b64) % 4) % 4, '=');
        $raw = base64_decode($padded, true);
        if ($raw === false) {
            throw new \RuntimeException('Invalid base64url string', 30);
        }
        return $raw;
    }
}
