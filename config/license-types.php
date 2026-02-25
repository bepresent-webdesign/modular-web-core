<?php

declare(strict_types=1);

/**
 * Canonical license types and their default rules.
 * Used by products and validated by ProductCatalog.
 */
return [
    'single_download' => [
        'default_max_downloads' => 1,
        'includes_updates' => false,
        'default_update_months' => 0,
    ],
    'standard_license' => [
        'default_max_downloads' => 3,
        'includes_updates' => false,
        'default_update_months' => 0,
    ],
    'update_entitled' => [
        'default_max_downloads' => 5,
        'includes_updates' => true,
        'default_update_months' => 12,
    ],
    'upgrade_conversion' => [
        'default_max_downloads' => 5,
        'includes_updates' => true,
        'default_update_months' => 12,
    ],
    'renewal' => [
        'default_max_downloads' => 5,
        'includes_updates' => true,
        'default_update_months' => 12,
    ],
];
