<?php

declare(strict_types=1);

/**
 * DSGVO helpers. Normalize and hash customer email for storage.
 */
final class Privacy
{
    /**
     * SHA256 hash of normalized email + pepper. For Purchase persistence only.
     */
    public static function hashCustomerEmail(string $email, string $pepper): string
    {
        $normalized = strtolower(trim($email));
        return hash('sha256', $normalized . $pepper);
    }
}
