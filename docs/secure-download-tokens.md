# Secure Download Tokens

HMAC-signed tokens for secure file downloads. Tokens are stateless and do not require database storage.

## Token Format

```
base64url(payload_json) . "." . base64url(hmac_sha256(payload_b64, secret))
```

The signature is computed over the **base64url-encoded payload string** (not raw JSON), so it is stable across encodings.

Verification uses `hash_equals` for constant-time comparison.

## Setting the Secret

The HMAC secret is loaded in this order:

1. **Environment variable** (preferred): `MODULAR_WEB_CORE_DOWNLOAD_TOKEN_SECRET`
2. **Config file fallback**: `config/secrets.php` returning `['download_token_secret' => '…']`

The secret must be at least **32 bytes**. Use a cryptographically random value in production.

### config/secrets.php

Create `config/secrets.php` (do **not** commit this file—it is gitignored). Copy from `config/secrets.php.example` and set a real secret:

```php
<?php
return [
    'download_token_secret' => 'YOUR_RANDOM_32_PLUS_BYTES_SECRET_HERE',
];
```

## Payload (Claims)

Required: `token_id`, `exp`, `file_key`, `engine_version`

Optional: `iat`, `iss`, `aud`, `nbf`

## Token Store (JSON)

For server-side revocation and one-time/max-download enforcement, tokens are persisted in a JSON file:

- **Store path**: `data/download_tokens.json` (runtime; `data/` is gitignored)
- **Revocation**: `TokenStoreJson::revoke($tokenId)` sets `status=revoked` and `revoked_at`
- **Max downloads**: `max_downloads` and `download_count` enforce one-time or limited downloads; when count reaches max, status becomes `consumed`
- **Locking**: All write operations use `flock(LOCK_EX)`; read-modify-write is atomic via temp file + `rename()` to ensure valid JSON and no partial writes

## Security Notes

- **Do not commit** `config/secrets.php`—it is in `.gitignore`
- **Do not log tokens**—log only `token_id` when needed for debugging
