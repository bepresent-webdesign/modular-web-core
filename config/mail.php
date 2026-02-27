<?php

declare(strict_types=1);

/**
 * Mail configuration.
 * mode: 'pretend' = no actual send (NullMailer); 'live' = real sending.
 */
return [
    'mode' => 'pretend',
    'from' => 'noreply@example.com',
    'from_name' => 'Modular Web Core',
    'subject_prefix' => '[Modular Web Core]',
    'support_contact' => 'support@example.com',
];
