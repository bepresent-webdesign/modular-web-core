# Product and License Model

Configuration and validation layer for products and Stripe price mapping. No Stripe API integration yet—only config and validators.

## Canonical Product IDs

| product_id | Name | Price | License Type |
|------------|------|-------|--------------|
| `starter_business_card` | Starter Business Card | 79€ | standard_license |
| `mini_cms_professional` | Mini CMS Professional | 149€ | update_entitled |
| `upgrade_starter_to_mini` | Upgrade: Starter to Mini CMS | 89€ | upgrade_conversion |
| `renewal_updates_12m` | Renewal: 12 Months Updates | 39€ | renewal |

## License Types

Defined in `config/license-types.php`:

| Type | Default max downloads | Includes updates | Default update months |
|------|----------------------|------------------|------------------------|
| `single_download` | 1 | no | 0 |
| `standard_license` | 3 | no | 0 |
| `update_entitled` | 5 | yes | 12 |
| `upgrade_conversion` | 5 | yes | 12 |
| `renewal` | 5 | yes | 12 |

## Stripe Price Mapping

Placeholder price IDs in `config/prices.stripe.php` (replace with real `price_xxx` IDs for production):

| Placeholder | Maps to | Amount |
|-------------|---------|--------|
| `price_test_starter_79` | starter_business_card | 79.00 € |
| `price_test_mini_149` | mini_cms_professional | 149.00 € |
| `price_test_upgrade_89` | upgrade_starter_to_mini | 89.00 € |
| `price_test_renewal_39` | renewal_updates_12m | 39.00 € |

Each mapping defines: `product_id`, `mode`, `currency`, `amount_minor`, `metadata_required`.

## Adding a New Product

1. Add license type to `config/license-types.php` if needed.
2. Add product to `config/products.php` with all required keys:
   - `product_id`, `name`, `description`, `license_type`, `max_downloads`
   - `includes_updates`, `update_months` (consistent: includes_updates true ⇒ update_months > 0)
   - `intended_zip`, `upgrade_from`, `upgrade_to`, `price_net_eur`
3. Run `php scripts/validate_catalog.php` to verify.

## Adding a New Price Mapping

1. Create the Stripe price in Stripe Dashboard (or use placeholder for dev).
2. Add entry to `config/prices.stripe.php`:
   ```php
   'price_xxx_actual_id' => [
       'product_id' => 'your_product_id',
       'mode' => 'payment',
       'currency' => 'eur',
       'amount_minor' => 7900,  // cents
       'metadata_required' => ['product_id', 'license_type'],
   ],
   ```
3. Ensure `amount_minor` matches product's `price_net_eur`.
4. Run `php scripts/validate_catalog.php` to verify.

## Validation Script

```bash
php scripts/validate_catalog.php
```

Exit 0 = all valid. Exit 1 = validation errors (printed to stderr).
