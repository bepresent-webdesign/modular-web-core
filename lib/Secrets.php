<?php

declare(strict_types=1);

/**
 * Secrets loader for Modular Web Core.
 * Reads HMAC secret from env first, then from config file fallback.
 */
final class Secrets
{
    private const ENV_DOWNLOAD_TOKEN_SECRET = 'MODULAR_WEB_CORE_DOWNLOAD_TOKEN_SECRET';
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
