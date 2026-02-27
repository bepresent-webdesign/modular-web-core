<?php

declare(strict_types=1);

/**
 * Application configuration (base URL, paths).
 * download_path: /public/download.php when doc root is project root, /download.php when doc root is public/
 * success_path, cancel_path: relative to base_url for checkout return URLs.
 */
return [
    'base_url' => 'https://example.com',
    'download_path' => '/public/download.php',
    'checkout_success_path' => '/public/checkout/success.php',
    'checkout_cancel_path' => '/public/checkout/cancel.php',
];
