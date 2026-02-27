<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

/**
 * Mailer using PHP mail().
 */
final class PhpMailMailer implements MailerInterface
{
    public function __construct(
        private string $from,
        private string $fromName = '',
    ) {
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: Modular-Web-Core',
        ];
        $fromHeader = $this->fromName !== ''
            ? 'From: ' . $this->formatHeaderValue($this->fromName) . ' <' . $this->from . '>'
            : 'From: ' . $this->from;
        $headers[] = $fromHeader;

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    private function formatHeaderValue(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
