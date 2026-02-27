# Stripe Webhook MVP (Issue #4)

Production-grade Stripe webhook endpoint for fulfilling orders and issuing download tokens.

## Required Environment Variables

| Variable | Description |
|----------|-------------|
| `STRIPE_WEBHOOK_SECRET` | Webhook signing secret (`whsec_...`). From `stripe listen` output or [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks). |

Optional for token creation (required if processing checkout.session.completed):

| Variable | Description |
|----------|-------------|
| `MODULAR_WEB_CORE_DOWNLOAD_TOKEN_SECRET` | HMAC secret for download tokens (or `config/secrets.php`). |

## Endpoint URL

```
http://localhost:8888/modular-web-core-php_v1_base/public/webhook/stripe.php
```

Adjust host/port/path for your environment.

## Running `stripe listen`

1. Install [Stripe CLI](https://stripe.com/docs/stripe-cli).

2. Start the listener, forwarding to your local endpoint:
   ```bash
   stripe listen --forward-to http://localhost:8888/modular-web-core-php_v1_base/public/webhook/stripe.php
   ```

3. Copy the webhook signing secret from the output:
   ```
   Ready! Your webhook signing secret is whsec_xxxxxxxxxxxx (^C to quit)
   ```

4. Set the env var before running your PHP server:
   ```bash
   export STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxx
   ```

## Triggering a Test Event

### Option 1: `stripe trigger`

```bash
stripe trigger checkout.session.completed
```

**Note:** The trigger creates a session with Stripe’s test price IDs (e.g. `price_1ABC...`). For fulfillment to work, you must map that price in `config/prices.stripe.php`. Add the price ID from the triggered event (or from Stripe Dashboard) to the config, for example:

```php
// config/prices.stripe.php – add the price ID from stripe trigger output
'price_1XXXXXXXX' => [
    'product_id' => 'starter_business_card',
    'mode' => 'payment',
    'currency' => 'eur',
    'amount_minor' => 7900,
    'metadata_required' => ['product_id', 'license_type'],
],
```

### Option 2: Event with session metadata (recommended)

When creating a real Checkout Session, set metadata:

```json
{
  "metadata": {
    "price_id": "price_test_starter_79"
  }
}
```

Then `checkout.session.completed` will include `metadata.price_id`, and the webhook can resolve it using `config/prices.stripe.php`. The endpoint also tries `line_items.data[0].price.id` if present in the payload (e.g. when Stripe includes expanded line items).

### Option 3: Manual curl with saved event

Save a sample event to `event.json` and send it:

```bash
# 1. Create event.json with a checkout.session.completed payload including:
#    - metadata.price_id (e.g. "price_test_starter_79")
#    - payment_status: "paid"
#    - data.object with id, customer_email, etc.

# 2. Sign with Stripe CLI (replace with your secret):
# stripe events resend <event_id>
# Or use a pre-signed payload from stripe trigger --save-to

# 3. Send (replace SIG with value from stripe listen or construct_event)
curl -X POST http://localhost:8888/modular-web-core-php_v1_base/public/webhook/stripe.php \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: t=TIMESTAMP,v1=SIGNATURE" \
  -d @event.json
```

To get a valid signature, use `stripe listen` and trigger an event, or sign manually per [Stripe webhook signature verification](https://docs.stripe.com/webhooks/signature).

## Where to Find Created Records

| Type | Path |
|------|------|
| Processed events (idempotency) | `storage/webhooks/processed-events.json` |
| Orders | `storage/orders/orders.json` |
| Licenses | `storage/licenses/licenses.json` |
| Download tokens | `data/download_tokens.json` |

The **download token string** is stored in each license’s `metadata.download_token` for delivery to the customer. Use it with:

```
GET /modular-web-core-php_v1_base/public/download.php?token=<token>
```

## Expected Behavior

| Scenario | HTTP Response |
|----------|----------------|
| Valid signature, first-time `checkout.session.completed` with `payment_status=paid` and valid `metadata.price_id` | `200` – Order, License, and Token created |
| Valid signature, duplicate event (already in processed-events) | `200` – No-op |
| Valid signature, `checkout.session.completed` but `payment_status` != paid | `200` – No-op |
| Valid signature, no `metadata.price_id` | `200` – No-op |
| Invalid/missing signature | `400` |
| Processing error (config, validation, etc.) | `500` |

## Architecture

- **Stripe-specific logic** under `app/Infrastructure/Payments/Stripe/`:
  - `StripeWebhookVerifier` – signature verification with timestamp tolerance
  - `StripePriceMap`, `StripePurchaseValidator` – purchase intent validation
- **Provider-agnostic stores**:
  - `WebhookEventStore` – idempotency
  - `OrdersJsonRepository`, `LicensesJsonRepository` – order and license storage
- **Token issuance** uses existing `TokenService` and `TokenStoreJson`.
