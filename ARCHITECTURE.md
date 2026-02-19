# Architecture Overview

## Core Concept

Modular Web Core is a file-based PHP engine built for controlled modular web systems.

It separates:

- Core logic
- Content layer (JSON)
- Template layer
- Admin layer
- Media handling
- Backup system

No database required.

---

## Structural Layers

1. **Core Engine**
   - bootstrap
   - routing
   - base_url handling

2. **Content Layer**
   - JSON-driven
   - site.json
   - impressum.json
   - datenschutz.json

3. **Admin Layer**
   - Authentication
   - Content editing
   - Media management
   - Backup / Restore

4. **Security Layer**
   - bcrypt password storage
   - rate limit
   - directory protection
   - CSRF validation

---

## Design Goals

- Stable small business deployments
- No plugin ecosystem
- No dependency cascade
- No update-chain fragility
- Controlled expansion via modular extensions

---

## Upgrade Path

Engine (Open Core)
↓
Modular BusinessCard CMS
↓
Mini CMS Feature Upgrade (planned)
