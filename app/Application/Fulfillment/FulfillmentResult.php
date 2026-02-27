<?php

declare(strict_types=1);

namespace App\Application\Fulfillment;

/**
 * Result of a fulfillment attempt.
 */
final class FulfillmentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $downloadUrl = null,
        public readonly ?string $licenseKey = null,
        public readonly ?string $lastErrorCode = null,
        public readonly ?string $lastErrorMessage = null,
    ) {
    }

    public static function success(string $downloadUrl, string $licenseKey): self
    {
        return new self(true, 'fulfilled', $downloadUrl, $licenseKey);
    }

    public static function failed(string $code, string $message): self
    {
        return new self(false, 'failed', null, null, $code, $message);
    }

    public static function idempotent(string $downloadUrl, string $licenseKey): self
    {
        return new self(true, 'fulfilled', $downloadUrl, $licenseKey);
    }
}
