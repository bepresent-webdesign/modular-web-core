<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

/**
 * Creates mailer based on config/mail.php.
 */
final class MailerFactory
{
    /**
     * @param array<string, mixed> $config From config/mail.php
     */
    public static function fromConfig(array $config): MailerInterface
    {
        $mode = $config['mode'] ?? 'pretend';
        if ($mode !== 'live') {
            return new PretendMailer();
        }
        return new PhpMailMailer(
            (string) ($config['from'] ?? 'noreply@example.com'),
            (string) ($config['from_name'] ?? ''),
        );
    }
}
