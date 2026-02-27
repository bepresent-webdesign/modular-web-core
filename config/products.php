<?php

declare(strict_types=1);

/**
 * Product catalog: canonical product_id and business properties.
 * Each product links to a license_type from config/license-types.php.
 */
return [
    'starter_business_card' => [
        'product_id' => 'starter_business_card',
        'name' => 'Starter Business Card',
        'description' => 'Single-site business card CMS',
        'license_type' => 'standard_license',
        'max_downloads' => 3,
        'includes_updates' => false,
        'update_months' => 0,
        'intended_zip' => 'modular-web-core-base.zip',
        'upgrade_from' => null,
        'upgrade_to' => 'mini_cms_professional',
        'price_net_eur' => 7900,
    ],
    'mini_cms_professional' => [
        'product_id' => 'mini_cms_professional',
        'name' => 'Mini CMS Professional',
        'description' => 'Full Mini CMS with content editing',
        'license_type' => 'update_entitled',
        'max_downloads' => 5,
        'includes_updates' => true,
        'update_months' => 12,
        'intended_zip' => 'modular-web-core-base.zip',
        'upgrade_from' => 'starter_business_card',
        'upgrade_to' => null,
        'price_net_eur' => 14900,
    ],
    'upgrade_starter_to_mini' => [
        'product_id' => 'upgrade_starter_to_mini',
        'name' => 'Upgrade: Starter to Mini CMS',
        'description' => 'Upgrade from Starter to Mini CMS Professional',
        'license_type' => 'upgrade_conversion',
        'max_downloads' => 5,
        'includes_updates' => true,
        'update_months' => 12,
        'intended_zip' => 'modular-web-core-base.zip',
        'upgrade_from' => 'starter_business_card',
        'upgrade_to' => 'mini_cms_professional',
        'price_net_eur' => 8900,
    ],
    'renewal_updates_12m' => [
        'product_id' => 'renewal_updates_12m',
        'name' => 'Renewal: 12 Months Updates',
        'description' => '12-month update entitlement renewal',
        'license_type' => 'renewal',
        'max_downloads' => 5,
        'includes_updates' => true,
        'update_months' => 12,
        'intended_zip' => 'modular-web-core-base.zip',
        'upgrade_from' => null,
        'upgrade_to' => null,
        'price_net_eur' => 3900,
    ],
];
