<?php

declare(strict_types=1);

namespace App\Infrastructure\License;

/**
 * Generates human-readable license keys.
 * Format: LIC-<24 hex chars> = 28 chars total.
 */
final class LicenseKeyGenerator
{
    private const PREFIX = 'LIC-';

    public function generate(): string
    {
        return self::PREFIX . strtoupper(bin2hex(random_bytes(12)));
    }
}
