<?php

declare(strict_types=1);

namespace App\Application\Checkout;

use Exception;

final class StripeCheckoutException extends Exception
{
    public function __construct(
        string $message,
        private string $codeKey = 'checkout_error',
    ) {
        parent::__construct($message);
    }

    public function getCodeKey(): string
    {
        return $this->codeKey;
    }
}
