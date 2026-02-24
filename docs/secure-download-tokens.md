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

## Download Endpoint

Secure download endpoint at `public/download.php`:

- **Token store path**: `CORE_ROOT/data/download_tokens.json` (gitignored)

- **Request**: `GET /download.php?token=...`
- **Success**: Streams ZIP with `Content-Disposition: attachment`, `Content-Type: application/zip`
- **Failure**: Returns 404 for invalid/expired/revoked/consumed/exceeded tokens (no info leakage)

### Allowed Files

Only `file_key = "engine_zip"` is allowed. Path is resolved as:

```
CORE_ROOT/dist/modular-web-core-{engine_version}.zip
```

`engine_version` must match semver pattern and the file must exist under `dist/`.

### curl Test Examples

```bash
# 1. Create a test token (manual, run once):
php scripts/dev_issue6_make_download_token.php

# 2. 200 with valid token:
curl -O -J "https://example.com/download.php?token=YOUR_TOKEN"

# 3. 404 on tampered token (flip one char in token):
curl -w "%{http_code}\n" "https://example.com/download.php?token=TAMPERED_TOKEN"

# 4. 404 on expired token (exp in past - use token from old run or manually expired):
curl -w "%{http_code}\n" "https://example.com/download.php?token=EXPIRED_TOKEN"

# 5. 404 on second download when max_downloads=1 (reuse same token after first successful download):
curl -w "%{http_code}\n" "https://example.com/download.php?token=ALREADY_CONSUMED_TOKEN"
```

## Security Notes

- **Do not commit** `config/secrets.php`—it is in `.gitignore`
- **Do not log tokens**—log only `token_id` when needed for debugging
