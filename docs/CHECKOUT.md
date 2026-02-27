# Checkout (Buy Flow)

Minimal Stripe Checkout Session Creator. No cookies, no sessions, no tracking. Always sets `metadata.product_id` and `metadata.license_type` for reliable webhook processing.

## Endpoint

`GET /public/buy.php?product_id=...&license_type=...`

Creates a Stripe Checkout Session and redirects (303) to Stripe Hosted Checkout.

## Input Whitelist

- **product_id**: Must exist in ProductCatalog
- **license_type**: Must match the product's license_type

Invalid input → HTTP 400 with short error message (no stack trace).

## Configuration

**config/app.php**:
```php
'base_url' => 'https://example.com',
'checkout_success_path' => '/public/checkout/success.php',
'checkout_cancel_path' => '/public/checkout/cancel.php',
```

**Stripe Secret Key**: ENV `STRIPE_SECRET_KEY` preferred, fallback `config/secrets.php` → `stripe_secret_key`.

## Return Pages

- **success**: `/public/checkout/success.php` – "Danke / Zahlung wird verarbeitet / du erhältst eine E-Mail"
- **cancel**: `/public/checkout/cancel.php` – "Zahlung abgebrochen"

No session, no cookies.

## CLI Validation Test

```bash
php cli/test_checkout_validation.php
```

Prüft Input-Whitelist und Price-Mapping (kein Stripe-API-Aufruf).

## Lokales Testen

### 1. Stripe CLI Listener

```bash
stripe listen --forward-to http://localhost:8888/webhook/stripe.php
```

### 2. Buy-URL im Browser

```
http://localhost:8888/public/buy.php?product_id=starter_business_card&license_type=standard_license
```

**Hinweis**: `license_type` muss dem Produkt entsprechen (z.B. `standard_license` für Starter, nicht `single`).

### 3. Testzahlung bei Stripe Checkout

- Testkarte: `4242 4242 4242 4242`
- Beliebiger zukünftiger Ablauf, beliebige CVC

### 4. Erwartung

- Webhook: `checkout.session.completed` wird verarbeitet
- Purchase wird angelegt
- Fulfillment: fulfilled
- Mail: je nach `config/mail.php` → `mode` (pretend/live)

## URL-Pfade anpassen (MAMP)

### Document Root = Projekt-Root (z.B. `/Users/.../modular-web-core-php_v1_base`)

- Buy: `http://localhost:8888/public/buy.php`
- Success: `http://localhost:8888/public/checkout/success.php`
- Webhook: `http://localhost:8888/public/webhook/stripe.php`

`base_url` in config: `http://localhost:8888`

### Document Root = `public/`

- Buy: `http://localhost:8888/buy.php`
- Success: `http://localhost:8888/checkout/success.php`
- Webhook: `http://localhost:8888/webhook/stripe.php`

`base_url`: `http://localhost:8888`  
`checkout_success_path`: `/checkout/success.php`  
`checkout_cancel_path`: `/checkout/cancel.php`

(Aus Sicht des Browsers sind die Pfade relativ zur Domain, nicht zum Dateisystem.)
