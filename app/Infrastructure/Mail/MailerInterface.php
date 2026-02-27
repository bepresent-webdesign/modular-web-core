<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

interface MailerInterface
{
    public function send(string $to, string $subject, string $body): bool;
}
