<?php

declare(strict_types=1);

/**
 * Stripe price ID → canonical purchase intent mapping.
 * Placeholder price IDs (replace with real Stripe price_xxx IDs for production).
 */
return [
    'price_1T55KjQ7VVOJqNoZHvdvH4yZ' => [
        'product_id' => 'starter_business_card',
        'mode' => 'payment',
        'currency' => 'eur',
        'amount_minor' => 7900,
        'metadata_required' => ['product_id', 'license_type'],
    ],
    'price_test_mini_149' => [
        'product_id' => 'mini_cms_professional',
        'mode' => 'payment',
        'currency' => 'eur',
        'amount_minor' => 14900,
        'metadata_required' => ['product_id', 'license_type'],
    ],
    'price_test_upgrade_89' => [
        'product_id' => 'upgrade_starter_to_mini',
        'mode' => 'payment',
        'currency' => 'eur',
        'amount_minor' => 8900,
        'metadata_required' => ['product_id', 'license_type'],
    ],
    'price_test_renewal_39' => [
        'product_id' => 'renewal_updates_12m',
        'mode' => 'payment',
        'currency' => 'eur',
        'amount_minor' => 3900,
        'metadata_required' => ['product_id', 'license_type'],
    ],
];
