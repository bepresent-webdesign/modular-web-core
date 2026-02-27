# Delivery Layer

Provider-agnostic foundation for purchase recording and fulfillment tracking. No PayPal or real email sending in this layer; it is designed to be extended by payment-provider handlers.

## Purchase Shape

```php
[
    'purchase_id'    => string,   // internal, stable (pur_<timestamp>_<random>)
    'provider'      => string,   // e.g. "stripe"
    'provider_event_id' => string,
    'customer_email' => ?string,
    'product_id'    => string,
    'license_type'  => string,
    'amount'        => int,      // minor units (e.g. cents)
    'currency'      => string,
    'created_at'    => string,   // ISO 8601
    'metadata'      => array,
]
```

- **purchase_id**: Internal ID, never changes. Used for idempotency and linking fulfillment.
- **provider** / **provider_event_id**: Store provider event reference for deduplication (e.g. Stripe event `evt_xxx`).

## Storage Layout

Directories are created **lazily** (on first write), like the Webhook MVP. All under `storage/`, which is gitignored.

| Path | Purpose |
|------|---------|
| `storage/purchases/purchases.json` | Purchase records, keyed by `purchase_id` |
| `storage/fulfillment/fulfillment.json` | Fulfillment status per `purchase_id` |
| `storage/email/` | Reserved for future outbox/log (no implementation in PR #1) |

### JSON Store Format

**purchases.json**:
```json
{
  "meta": { "version": 1, "updated_at": "..." },
  "purchases": {
    "pur_1730123456_abc123": { ... }
  }
}
```

**fulfillment.json**:
```json
{
  "meta": { "version": 1, "updated_at": "..." },
  "fulfillments": {
    "pur_1730123456_abc123": {
      "purchase_id": "pur_1730123456_abc123",
      "status": "fulfilled",
      "updated_at": "...",
      "metadata": {}
    }
  }
}
```

## Idempotency / Fulfillment Concept

1. **Purchase creation**  
   Before creating a purchase, check `getByProviderEvent(provider, provider_event_id)`. If a purchase exists, skip creation (idempotency).

2. **Fulfillment status**  
   One row per `purchase_id`. Status values: `pending`, `fulfilled`, `failed`, etc.  
   `metadata` can hold `error`, `email_sent_at`, etc.

3. **Atomic writes**  
   All repositories use `flock(LOCK_EX)`, temp file, and `rename()` for atomic replace—same pattern as `OrdersJsonRepository`, `WebhookEventStore`.

## Components

| Class | Namespace | Purpose |
|-------|-----------|---------|
| `Purchase` | `App\Domain\Purchase` | Value object: `generateId()`, `create(array)` |
| `PurchaseRepository` | `App\Infrastructure\Purchases` | JSON store for purchases |
| `FulfillmentRepository` | `App\Infrastructure\Fulfillment` | JSON store for fulfillment status (extended in PR #2) |
| `FulfillmentService` | `App\Application\Fulfillment` | Synchronous fulfillment (license, token, email) |

## Usage Example

```php
use App\Domain\Purchase\Purchase;
use App\Infrastructure\Purchases\PurchaseRepository;
use App\Infrastructure\Fulfillment\FulfillmentRepository;

$storageRoot = CORE_ROOT . '/storage';
$purchaseRepo = new PurchaseRepository($storageRoot . '/purchases/purchases.json');
$fulfillmentRepo = new FulfillmentRepository($storageRoot . '/fulfillment/fulfillment.json');

// Idempotency: skip if already processed
$existing = $purchaseRepo->getByProviderEvent('stripe', $eventId);
if ($existing !== null) {
    return; // already handled
}

$purchase = Purchase::create([
    'provider' => 'stripe',
    'provider_event_id' => $eventId,
    'customer_email' => $customerEmail,
    'product_id' => $productId,
    'license_type' => $licenseType,
    'amount' => 1999,
    'currency' => 'eur',
]);
$purchaseRepo->create($purchase);

$fulfillmentRepo->setStatus($purchase['purchase_id'], 'pending');
// ... later ...
$fulfillmentRepo->setStatus($purchase['purchase_id'], 'fulfilled');
```

## FulfillmentService (PR #2)

For full fulfillment flow (license key, download token, email), see `docs/FULFILLMENT.md`.

## Testing

```bash
php cli/test_delivery_foundation.php   # Purchase + FulfillmentRepository basics
php cli/test_fulfillment.php          # FulfillmentService (pretend mail mode)
```
