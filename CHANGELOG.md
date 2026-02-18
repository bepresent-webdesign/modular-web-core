# Changelog

## v0.3.0 – Stable Engine + Admin Layer (2026-02-18)

### Added
- Setup wizard with lockfile protection
- Admin authentication (bcrypt, session hardening, rate limit)
- JSON-based content management
- Media upload with MIME validation
- Automatic WebP + thumbnail generation (GD fallback)
- Trash-based delete system
- Automatic and manual backup system
- Superadmin role support
- Secure routing (.htaccess + built-in router)

### Improved
- Subfolder-safe base_url() handling
- Canonical & OG meta generation
- Content normalization and path validation

### Not included
- Payment integration
- Automated download delivery
- Feature gating (BusinessCard vs Mini CMS)

---

## v0.1.0 – Initial Structure
- Base architecture
- Core routing
- JSON content system
