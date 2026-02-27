# Commercial Delivery Playbook

**Version:** v1.x  
**Project:** Modular Web Core  
**Architecture:** PHP 8+, file-based, MVC-light, no framework

---

# 1. System Architecture Overview

## Complete Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  BUY URL                                                                    │
│  GET /public/buy.php?product_id=...&license_type=...                        │
└────────────────────────────────┬────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  StripeCheckoutService                                                       │
│  - Validates product_id, license_type (whitelist)                           │
│  - Resolves price_id via StripePriceMap                                     │
│  - Creates Stripe Checkout Session via API                                  │
│  - Redirects 303 to Stripe Hosted Checkout                                 │
└────────────────────────────────┬────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Stripe Hosted Checkout                                                     │
│  - Customer pays (4242 4242 4242 4242 for test)                             │
│  - Stripe collects email (no pre-auth required)                             │
└────────────────────────────────┬────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Stripe Webhook                                                              │
│  POST /public/webhook/stripe.php                                            │
│  - Verifies signature (rawBody + whsec_)                                     │
│  - Idempotency: WebhookEventStore + PurchaseRepository.getByProviderEvent   │
│  - Extracts session data → Purchase shape                                   │
│  - product_id/license_type: metadata first, then price map fallback          │
└────────────────────────────────┬────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  PurchaseRepository (storage/purchases/purchases.json)                       │
│  - Atomic write + flock(LOCK_EX)                                             │
│  - Key: purchase_id (pur_<timestamp>_<random>)                              │
└────────────────────────────────┬────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  FulfillmentService::fulfill($purchase)                                      │
│  - Idempotent: if already fulfilled → return success                        │
│  - setStatus(processing)                                                    │
│  - LicenseKeyGenerator (LIC-<24 hex>)                                       │
│  - TokenService (HMAC, 7 days expiry, max_downloads from catalog)           │
│  - TokenStoreJson (data/download_tokens.json)                               │
│  - MailerInterface (PretendMailer or PhpMailMailer)                         │
│  - setStatus(fulfilled) + license_key_hash, token_id                         │
└────────────────────────────────┬────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Download Endpoint                                                            │
│  GET /public/download.php?token=...                                          │
│  - TokenService::verify, TokenStoreJson::consume                            │
│  - Streams ZIP from dist/                                                   │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Key Architectural Decisions

| Aspect | Implementation |
|--------|----------------|
| Framework | None. Plain PHP. |
| Sessions | Not required. Stateless buy flow. |
| Cookies | Not required. No tracking. |
| Background worker | None. Fulfillment runs synchronously in webhook. |
| Cron | None. Shared hosting compatible. |
| Storage | JSON files with `flock(LOCK_EX)` and temp file + `rename()` for atomic writes. |
| Idempotency | `provider_event_id` (Stripe evt_xxx) + `PurchaseRepository.getByProviderEvent()`. |

---

# 2. Required Configuration Files

## config/app.php

Defines base URL and path suffixes. **Critical:** `base_url` must match your actual deployment. Wrong base_url → success/cancel redirects to example.com instead of your domain.

```php
return [
    'base_url' => 'https://example.com',
    'download_path' => '/public/download.php',
    'checkout_success_path' => '/public/checkout/success.php',
    'checkout_cancel_path' => '/public/checkout/cancel.php',
];
```

**Path semantics:**
- `base_url` + `checkout_success_path` = absolute success URL for Stripe
- `download_path` is appended to `base_url` for fulfillment email links

## config/secrets.php

**Gitignored.** Never commit. Copy from `config/secrets.php.example`.

```php
return [
    'download_token_secret' => 'CHANGE_ME_32_PLUS_BYTES',
    'stripe_webhook_secret' => 'whsec_...',
    'stripe_secret_key' => 'sk_test_...',
];
```

- `download_token_secret`: HMAC for download tokens. Min 32 bytes.
- `stripe_webhook_secret`: From `stripe listen` output. Used for signature verification.
- `stripe_secret_key`: Stripe API key (sk_test_... or sk_live_...).

**Syntax check:**
```bash
php -l config/secrets.php
```

## config/prices.stripe.php

Maps Stripe **price IDs** to internal product mapping. Array key **must** be the exact Stripe price ID.

```php
return [
    'price_1T55KjQ7VVOJqNoZHvdvH4yZ' => [
        'product_id' => 'starter_business_card',
        'mode' => 'payment',
        'currency' => 'eur',
        'amount_minor' => 7900,
        'metadata_required' => ['product_id', 'license_type'],
    ],
];
```

## config/mail.php

```php
return [
    'mode' => 'pretend',  // 'pretend' | 'live'
    'from' => 'noreply@example.com',
    'from_name' => 'Modular Web Core',
    'subject_prefix' => '[Modular Web Core]',
    'support_contact' => 'support@example.com',
];
```

## Document Root Setup

### MAMP: Document Root = Project Root

- URLs: `http://localhost:8888/public/buy.php`, `http://localhost:8888/public/webhook/stripe.php`
- `base_url`: `http://localhost:8888`
- Paths in config: `/public/checkout/success.php`, `/public/download.php`

### MAMP: Document Root = public/

- URLs: `http://localhost:8888/buy.php`, `http://localhost:8888/webhook/stripe.php`
- `base_url`: `http://localhost:8888`
- Paths: `checkout_success_path` → `/checkout/success.php`, `download_path` → `/download.php`

---

# 3. Stripe Setup – Complete Detailed Guide

## 3.1 Stripe Dashboard Setup

### Step-by-step

1. **Enable Test Mode**  
   Toggle in Stripe Dashboard top-right. All test IDs use `_test_` or `pk_test_`, `sk_test_`, `whsec_...` (webhook secret has no _test_ in ID but belongs to test mode session).

2. **Create Product**  
   Products → Add product. You get:
   - `prod_xxxxxxxxxxxxx` – Product ID (not used as price in this project)

3. **Create Price**  
   On the product, add a price:
   - **Currency:** EUR (or your choice; must match `config/prices.stripe.php`)
   - **One-time**
   - Copy the **Price ID**: `price_xxxxxxxxxxxxx`

4. **ID types – common confusion**

   | Prefix | Purpose |
   |--------|---------|
   | `prod_` | Product (bundle of prices). Not used in buy.php directly. |
   | `price_` | **This is what you need.** Used in `config/prices.stripe.php` and Checkout. |
   | `pk_test_` | Publishable key (client-side). Not used in this server-only flow. |
   | `sk_test_` | Secret key (server-side). Used for API calls (buy.php, etc.). |
   | `whsec_` | Webhook signing secret. From `stripe listen` for local; from Dashboard for production. |

---

## 3.2 Stripe API Keys

- **Publishable key (`pk_test_`)**: For client-side Stripe.js. This project does not use it.
- **Secret key (`sk_test_`)**: Required for creating Checkout Sessions and other server-side API calls. Load order:
  1. `STRIPE_SECRET_KEY` env var
  2. `config/secrets.php` → `stripe_secret_key`

**Never commit `config/secrets.php`.** It is listed in `.gitignore`.

**Verify syntax:**
```bash
php -l config/secrets.php
```

**Real error encountered:** Missing closing quote in `stripe_secret_key`:
```php
'stripe_secret_key' => 'sk_test_abc123 ,  // BUG: missing '
```
Result: PHP parse error or invalid config → "Configuration error" when loading secrets. Fix: ensure all strings are properly quoted and terminated.

---

## 3.3 Stripe Price Mapping

The array key in `config/prices.stripe.php` **must** be the exact Stripe price ID.

**Common errors:**

1. **Placeholder not replaced**  
   `price_test_starter_79` is a placeholder. Replace with real `price_1T55...` from Stripe.

2. **Copy/paste errors**  
   `1T55` vs `1T5S` (0 vs O, 5 vs S). Stripe returns: `No such price: 'price_XXXX'`.

3. **Stripe CLI account mismatch**  
   `stripe listen` and Dashboard may use different accounts. Verify with:
   ```bash
   stripe config --list
   ```

**Troubleshooting:**
```bash
stripe prices retrieve price_1T55KjQ7VVOJqNoZHvdvH4yZ
```
- Success: Price exists.  
- Error: Wrong ID or wrong Stripe account. Fix the ID in `config/prices.stripe.php`.

---

## 3.4 Stripe CLI Testing (Local)

### Webhook forwarding

```bash
stripe listen --forward-to http://localhost:8888/public/webhook/stripe.php
```

Output includes:
```
Ready! Your webhook signing secret is whsec_xxxxxxxxxxxxxxxxxxxxxxxx
```

Copy this `whsec_` value into `config/secrets.php`:

```php
'stripe_webhook_secret' => 'whsec_xxxxxxxxxxxxxxxxxxxxxxxx',
```

**Why config fallback:** Under MAMP, Apache does not inherit terminal environment variables. `export STRIPE_WEBHOOK_SECRET=whsec_...` in the shell does not reach PHP. Use `config/secrets.php` for local development.

### Full local test checklist

1. **Validate PHP config**
   ```bash
   php -l config/secrets.php
   ```

2. **Verify price exists**
   ```bash
   stripe prices retrieve price_YOUR_ID
   ```

3. **Test buy endpoint**
   ```bash
   curl -I "http://localhost:8888/public/buy.php?product_id=starter_business_card&license_type=standard_license"
   ```
   Expect: `303` redirect to Stripe Checkout URL.

4. **Start webhook listener**
   ```bash
   stripe listen --forward-to http://localhost:8888/public/webhook/stripe.php
   ```

5. **Complete test payment**  
   Use card `4242 4242 4242 4242`, future expiry, any CVC.

6. **Verify fulfillment**
   ```bash
   cat storage/fulfillment/fulfillment.json
   ```
   Expect: entry with `status: fulfilled`.

---

## 3.5 Webhook Processing Details

- **Idempotency:** `PurchaseRepository.getByProviderEvent('stripe', $eventId)`. If a purchase already exists for this event, reuse it. No duplicate creates.
- **Event idempotency:** `WebhookEventStore` tracks processed event IDs. Duplicate events return HTTP 200 without reprocessing.
- **HTTP 200 on failure:** If fulfillment throws or fails, the webhook sets `FulfillmentRepository` status to `failed` and still returns HTTP 200. Prevents Stripe retry storms.
- **Metadata vs price map:** `product_id` and `license_type` are resolved from:
  1. `session.metadata.product_id`, `session.metadata.license_type`
  2. Fallback: `line_items[0].price.id` → `StripePriceMap` → catalog
- **Logging:** No plaintext email in logs. Use hash or omit.

---

# 4. Fulfillment Layer

## Components

| Component | Purpose |
|-----------|---------|
| `FulfillmentService` | Orchestrates license, token, email. Idempotent on already-fulfilled. |
| `LicenseKeyGenerator` | `LIC-` + 24 hex chars. Human-readable. |
| `TokenService` | HMAC-signed download tokens. |
| `TokenStoreJson` | Persists token state (max_downloads, download_count). |
| `FulfillmentRepository` | Stores `status`, `license_key_hash`, `token_id`, `delivered_at`. |

## Token details

- **Validity:** 7 days (configurable in FulfillmentService).
- **max_downloads:** From product catalog (e.g. 3, 5).
- **Replay protection:** Token consumed on each GET download; `download_count` incremented until max.
- **Storage:** `license_key_hash` (sha256) stored; plaintext license key never persisted.

---

# 5. Email Layer

- **MailerInterface:** `send(string $to, string $subject, string $body): bool`
- **PretendMailer:** No-op. Returns `true`, never sends.
- **PhpMailMailer:** Uses PHP `mail()`.
- **MailerFactory::fromConfig():** Reads `config/mail.php` → `mode`. If `mode !== 'live'`, returns PretendMailer.

**Why pretend by default:** Local and CLI tests must not send real emails. `mode=pretend` is safe default.

---

# 6. Real Issues Encountered (Lessons Learned)

| Problem | Symptom | Root Cause | Fix | Prevention |
|---------|---------|------------|-----|------------|
| Missing quote in secrets.php | "Configuration error" or parse error | `'stripe_secret_key' => 'sk_xxx` (missing closing `') | Close string, verify with `php -l config/secrets.php` | Always run `php -l` after editing secrets |
| Wrong price ID (55 vs 5S) | `No such price: 'price_XXX'` | Copy/paste typo | `stripe prices retrieve price_XXX` to verify | Copy directly from Stripe Dashboard |
| example.com redirect | Success/cancel goes to example.com | `base_url` left as `https://example.com` | Set `base_url` to actual domain (e.g. `http://localhost:8888`) | Document base_url in deployment checklist |
| Stripe CLI wrong account | Price lookup fails | `stripe` CLI logged into different account than Dashboard | `stripe config --list`, re-login | Use same Stripe account for CLI and Dashboard |
| Placeholder price IDs | Webhook can't resolve product | `price_test_starter_79` not real Stripe ID | Replace with real `price_1T55...` from Stripe | Validate catalog script; checklist |
| Metadata missing | Webhook marks purchase invalid | Checkout session has no metadata | buy.php always sets `metadata.product_id`, `metadata.license_type` | Enforced in StripeCheckoutService |
| ENV vars not in MAMP | Webhook 400, CLI works | Apache does not inherit shell env | Use `config/secrets.php` fallback | All secrets support config fallback |
| Document root confusion | 404 on buy.php or webhook | Wrong URL path | Align base_url and paths with doc root setup | Document both setups (project root vs public/) |

---

# 7. Production Deployment Checklist

- [ ] Replace `sk_test_` with `sk_live_` in `config/secrets.php` (or `STRIPE_SECRET_KEY`)
- [ ] Register webhook endpoint in Stripe Dashboard (Developers → Webhooks) with production URL
- [ ] Copy production `whsec_` from Dashboard into `stripe_webhook_secret`
- [ ] Enforce HTTPS (Stripe requires it for production webhooks)
- [ ] Replace `download_token_secret` with new cryptographically random value
- [ ] Set `base_url` to production domain (e.g. `https://yourdomain.com`)
- [ ] Verify webhook signature validation is active (no debug bypass)
- [ ] Rotate any exposed test keys
- [ ] Set `config/mail.php` `mode` to `live` and configure `from`, `from_name`, `support_contact`
- [ ] Ensure `storage/` and `data/` are writable and gitignored

---

# 8. Minimal DSGVO Strategy

| Aspect | Implementation |
|--------|----------------|
| Cookies | Not required. No tracking cookies. |
| Tracking | None. No analytics, no pixels. |
| Stored data | Only contract-required: purchase_id, product_id, license_type, amount, currency, customer_email (for delivery). |
| IP logging | Not implemented. |
| JSON storage | Under `storage/`, `data/`. Gitignored. |
| License key | Stored as `license_key_hash` (sha256), never plaintext. |
| Data minimization | No marketing data. No profiling. |

---

# 9. Future Extensions

- **PayPal:** Add provider-agnostic handler similar to Stripe. Purchase shape already supports `provider` + `provider_event_id`. FulfillmentService remains unchanged.
- **Update delivery:** CLI script to re-fulfill or extend tokens. No cron; run on demand.
- **Subscription model:** Would require Stripe Subscription handling and separate fulfillment logic for recurring deliveries.
