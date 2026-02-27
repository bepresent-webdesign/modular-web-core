# Fulfillment Service

Synchronous, provider-agnostic fulfillment for commercial delivery. No cron, no queue.

## FulfillmentService

`App\Application\Fulfillment\FulfillmentService::fulfill(array $purchase): FulfillmentResult`

**Flow**:
1. Idempotent: if `FulfillmentRepository` already has `status=fulfilled` for this purchase → return success, no side effects.
2. Set status to `processing`.
3. Generate license key (LIC-<24 hex chars>).
4. Create download token (7 days expiry, max_downloads from product catalog).
5. Store token in `TokenStoreJson` (metadata without PII).
6. Send delivery email (if mailer is live and customer_email present).
7. Set status to `fulfilled` with `license_key_hash` (sha256), `token_id`, `delivered_at`.

**On error**: Set status to `failed` with `last_error_code`, `last_error_message`.

## FulfillmentRepository Schema

Per `purchase_id`:
- `status`: pending | processing | fulfilled | failed
- `delivered_at`: ISO 8601 when fulfilled
- `attempt_count`: incremented when entering processing
- `last_error_code`, `last_error_message`: on failure
- `token_id`: links to TokenStoreJson
- `license_key_hash`: sha256 hex (never store plaintext key)

## Mail

- **Interface**: `App\Infrastructure\Mail\MailerInterface`
- **Implementations**: `PhpMailMailer` (mail()), `PretendMailer` (no-op)

**config/mail.php**:
```php
return [
    'mode' => 'pretend',  // 'pretend' | 'live'
    'from' => 'noreply@example.com',
    'from_name' => 'Modular Web Core',
    'subject_prefix' => '[Modular Web Core]',
    'support_contact' => 'support@example.com',
];
```

**mode=live**: Set this to send real emails. Use `PretendMailer` / `mode=pretend` for CLI tests and local dev.

## CLI Test

```bash
php cli/test_fulfillment.php
```

Uses `config/mail.php` → `mode=pretend` by default (no real send).

**Activate real mail**: Edit `config/mail.php` and set `'mode' => 'live'`, then configure `from`, `from_name` for your environment.

## Stripe Webhook (PR #3)

The webhook at `public/webhook/stripe.php` converts Stripe `checkout.session.completed` events into provider-agnostic Purchases and calls `FulfillmentService->fulfill()` synchronously.

### Test locally with Stripe CLI

1. Install [Stripe CLI](https://stripe.com/docs/stripe-cli).

2. Login and forward webhooks to your local server:
   ```bash
   stripe listen --forward-to http://localhost:8888/webhook/stripe.php
   ```

3. In a second terminal, trigger a test event:
   ```bash
   stripe trigger checkout.session.completed
   ```

4. Ensure `config/secrets.php` has the `whsec_...` from the `stripe listen` output (or set `STRIPE_WEBHOOK_SECRET` env var for the web server).

5. For price mapping: Stripe test events may not include your price IDs. Either:
   - Configure Checkout with metadata `product_id`, `license_type` (and optionally `price_id`), or
   - Use `config/prices.stripe.php` to map the triggered price ID.

6. If your web root is `public/`, adjust the forward URL (e.g. `http://localhost:8888/public/webhook/stripe.php` or just `/webhook/stripe.php` depending on MAMP setup).

### CLI sim (no Stripe)

```bash
php cli/test_webhook_fulfillment_sim.php
```

Creates a fake Purchase and runs fulfill(). No network, no Stripe.

## Buy Flow

Checkout Session Creator: `public/buy.php`. Siehe `docs/CHECKOUT.md`.
