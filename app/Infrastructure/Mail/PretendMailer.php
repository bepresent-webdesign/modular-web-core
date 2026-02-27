<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

/**
 * No-op mailer for dev/testing. Never sends mail.
 */
final class PretendMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $body): bool
    {
        return true;
    }
}
