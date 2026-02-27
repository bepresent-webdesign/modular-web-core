# Project Learnings

This document captures architectural pitfalls, debugging lessons and environment-specific issues discovered during development of Modular Web Core.
It serves as a blueprint for future commercial PHP projects.

---

## Stripe Webhook – 400 Signature Failure under MAMP (2-Day Debug Case)

### Problem
Stripe CLI delivered events successfully.
Webhook endpoint responded with HTTP 400 for all events.
No storage/ directory was created.

### Root Cause
Environment variable STRIPE_WEBHOOK_SECRET was exported in terminal,
but Apache (MAMP) PHP runtime does NOT inherit terminal environment variables.

lib/Secrets.php::stripeWebhookSecret() only checked getenv()
and therefore returned empty secret inside the web process.

Signature verification failed → HTTP 400.

### Solution
Modify stripeWebhookSecret() to:

1. Check getenv('STRIPE_WEBHOOK_SECRET')
2. Fallback to config/secrets.php['stripe_webhook_secret']
3. Enforce minimum length validation
4. Throw RuntimeException only if both sources missing

After fix:
Webhook returned HTTP 200
storage/ directory was created
Orders, licenses and tokens were persisted correctly.

## Key Lesson
Never rely solely on terminal environment variables when using Apache/MAMP.
Web and CLI environments are isolated.
Always implement config fallback for secrets in local development.

### Preventive Rule for Future Projects
All secrets must support:
- ENV variable priority
- Config file fallback (local only, gitignored)
- Strict validation

---

## 5. Environment Separation (CLI vs Web Runtime)

### Observation
Terminal environment variables (export VAR=value) are NOT available inside Apache/MAMP PHP runtime.

### Impact
Webhook secret mismatch caused permanent HTTP 400.
CLI tests succeeded, but web runtime failed.

### Rule
Never assume CLI and Apache share environment state.

All secrets must support:
- getenv() priority
- config file fallback
- strict validation

---

## 6. Stripe Local Development Checklist

Before testing webhooks:

1. Run exactly ONE stripe listen process.
2. Copy the whsec value from that session.
3. Ensure config/secrets.php contains the same whsec.
4. Confirm stripeWebhookSecret() loads ENV OR config fallback.
5. Trigger event immediately after starting stripe listen.

If webhook returns 400:
- Check secret mismatch
- Check verification algorithm
- Check timestamp tolerance

---

## 7. Storage Creation Behavior

The storage/ directory is created only after successful signature verification and event processing.

If storage does not exist:
- Webhook is failing before business logic
- Always inspect HTTP status in stripe listen

---

## 8. Architectural Principle Applied

Security-critical systems must fail closed (400 on signature failure).

However, debugging must:
- Separate secret-loading issues from verification issues
- Isolate environment boundaries
- Log root cause immediately after resolution

---

*This document must be updated after every non-trivial debugging session. It represents institutional knowledge and prevents future regressions.*

---

## 9. Stripe Webhook + Fulfillment (PR #3)

### Flow
Stripe `checkout.session.completed` → Purchase (provider-agnostic) → FulfillmentService → license, token, email.

### Local test with Stripe CLI
```bash
stripe listen --forward-to http://localhost:8888/webhook/stripe.php
# In another terminal:
stripe trigger checkout.session.completed
```
See `docs/FULFILLMENT.md` for full Stripe CLI setup.
