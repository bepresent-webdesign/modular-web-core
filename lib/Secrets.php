<?php

declare(strict_types=1);

/**
 * Secrets loader for Modular Web Core.
 * Reads HMAC secret from env first, then from config file fallback.
 */
final class Secrets
{
    private const ENV_DOWNLOAD_TOKEN_SECRET = 'MODULAR_WEB_CORE_DOWNLOAD_TOKEN_SECRET';
    private const ENV_STRIPE_WEBHOOK_SECRET = 'STRIPE_WEBHOOK_SECRET';
    private const ENV_STRIPE_SECRET_KEY = 'STRIPE_SECRET_KEY';
    private const MIN_SECRET_LENGTH = 32;

    /**
     * Returns the HMAC secret for download tokens.
     * Checks env var first, then config/secrets.php.
     *
     * @throws \RuntimeException if missing or too short (< 32 bytes)
     */
    public static function downloadTokenSecret(): string
    {
        $secret = getenv(self::ENV_DOWNLOAD_TOKEN_SECRET);
        if ($secret !== false && $secret !== '') {
            if (strlen($secret) < self::MIN_SECRET_LENGTH) {
                throw new \RuntimeException(
                    'Download token secret from env is too short (min 32 bytes)',
                    1
                );
            }
            return $secret;
        }

        $configPath = (defined('CORE_ROOT') ? CORE_ROOT : dirname(__DIR__)) . '/config/secrets.php';
        if (!is_readable($configPath)) {
            throw new \RuntimeException(
                'Download token secret missing. Set ' . self::ENV_DOWNLOAD_TOKEN_SECRET . ' or create config/secrets.php',
                2
            );
        }

        $config = require $configPath;
        if (!is_array($config)) {
            throw new \RuntimeException('config/secrets.php must return an array', 3);
        }

        $secret = $config['download_token_secret'] ?? '';
        if (!is_string($secret) || strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new \RuntimeException(
                'download_token_secret in config/secrets.php missing or too short (min 32 bytes)',
                4
            );
        }

        return $secret;
    }

    /**
     * Returns the Stripe webhook signing secret (whsec_...).
     * Checks env var first, then config/secrets.php key 'stripe_webhook_secret'.
     *
     * @throws \RuntimeException if missing or too short (< 32 bytes)
     */
    public static function stripeWebhookSecret(): string
    {
        $secret = getenv(self::ENV_STRIPE_WEBHOOK_SECRET);
        if ($secret !== false && $secret !== '') {
            if (strlen($secret) < self::MIN_SECRET_LENGTH) {
                throw new \RuntimeException(
                    'Stripe webhook secret from env is too short (min 32 bytes)',
                    5
                );
            }
            return $secret;
        }

        $configPath = (defined('CORE_ROOT') ? CORE_ROOT : dirname(__DIR__)) . '/config/secrets.php';
        if (!is_readable($configPath)) {
            throw new \RuntimeException(
                'Stripe webhook secret missing. Set STRIPE_WEBHOOK_SECRET or create config/secrets.php with stripe_webhook_secret',
                6
            );
        }

        $config = require $configPath;
        if (!is_array($config)) {
            throw new \RuntimeException('config/secrets.php must return an array', 7);
        }

        $secret = $config['stripe_webhook_secret'] ?? '';
        if (!is_string($secret) || $secret === '') {
            throw new \RuntimeException(
                'stripe_webhook_secret missing. Set STRIPE_WEBHOOK_SECRET or add stripe_webhook_secret to config/secrets.php',
                8
            );
        }
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new \RuntimeException(
                'stripe_webhook_secret in config/secrets.php is too short (min 32 bytes)',
                9
            );
        }

        return $secret;
    }

    /**
     * Returns the Stripe Secret Key (sk_...) for API calls.
     * Checks env var first, then config/secrets.php key 'stripe_secret_key'.
     *
     * @throws \RuntimeException if missing
     */
    public static function stripeSecretKey(): string
    {
        $key = getenv(self::ENV_STRIPE_SECRET_KEY);
        if ($key !== false && $key !== '' && str_starts_with($key, 'sk_')) {
            return $key;
        }

        $configPath = (defined('CORE_ROOT') ? CORE_ROOT : dirname(__DIR__)) . '/config/secrets.php';
        if (!is_readable($configPath)) {
            throw new \RuntimeException(
                'Stripe secret key missing. Set STRIPE_SECRET_KEY or create config/secrets.php with stripe_secret_key',
                10
            );
        }

        $config = require $configPath;
        if (!is_array($config)) {
            throw new \RuntimeException('config/secrets.php must return an array', 11);
        }

        $key = $config['stripe_secret_key'] ?? '';
        if (!is_string($key) || $key === '' || !str_starts_with($key, 'sk_')) {
            throw new \RuntimeException(
                'stripe_secret_key missing or invalid. Set STRIPE_SECRET_KEY or add stripe_secret_key (sk_...) to config/secrets.php',
                12
            );
        }

        return $key;
    }
}

/**
 * Returns the HMAC secret for download tokens (env or config fallback).
 *
 * @throws \RuntimeException if missing or too short (< 32 bytes)
 */
function download_token_secret(): string
{
    return Secrets::downloadTokenSecret();
}
