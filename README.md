<<<<<<< HEAD
# Universal Core Mini-CMS

File-basiertes Mini-CMS für Shared Webhosting (IONOS, Strato, 1&1, all-inkl etc.). Kein Node, keine MySQL – nur PHP und JSON. Nach FTP-Upload Installation im Browser unter `/setup`, danach Inhalte unter `/admin` pflegen.

## Anforderungen

- PHP 7.4+ (mit GD für WebP/Thumbnails, optional)
- Apache mit `mod_rewrite` oder Nginx mit passender Konfiguration; alternativ PHP Built-in-Server zum Testen

## Schnellstart (lokal)

```bash
# Im Projektordner
php -S localhost:8080 router.php
```

Dann im Browser:

1. **https://localhost:8080/setup** – Setup ausführen (E-Mail + Passwort), Installation sperrt sich danach
2. **https://localhost:8080** – Website ansehen
3. **https://localhost:8080/admin** – Einloggen, Inhalte und Medien verwalten, Backup/Restore

## Installation beim Kunden (FTP + Browser)

1. Alle Projektdateien per FTP in das gewünschte Verzeichnis (z. B. `htdocs` oder `public_html`) hochladen.
2. **Schreibrechte** setzen (typisch **755** für Ordner, **644** für Dateien; Schreibzugriff für den Webserver auf):
   - `data/`
   - `content/`
   - `uploads/` (inkl. `uploads/images/`, `uploads/trash/`)
   - `backups/`
3. Im Browser **https://ihre-domain.tld/setup** aufrufen.
4. Admin-E-Mail und Passwort (mit Bestätigung) eingeben und Installation starten.
5. Nach Abschluss: Setup ist deaktiviert (Lockfile). Website unter **/** nutzen, Admin unter **/admin**.

Details und Fehlerbehebung (403/500, Dateirechte) siehe **Deployment.md**.

## Architektur (v1.x – bereinigt)

- **Inhalte**: Ausschließlich JSON in `/content` (content.json, impressum.json, datenschutz.json)
- **Darstellung**: Frontend über `index.php` → `templates/home.php` oder `templates/page.php`
- **Admin-Bearbeitung**: `/admin/content.php?page=site|impressum|datenschutz`
- **Routing**: Zentral in `router.php` (PHP Built-in-Server) bzw. `.htaccess` (Apache)
- **Impressum/Datenschutz**: Keine separaten PHP-Dateien. Links verwenden `index.php?page=impressum` (funktioniert ohne mod_rewrite). Saubere URLs `/impressum` und `/datenschutz` via .htaccess möglich.

## Projektstruktur

```
├── index.php              # Frontend (Startseite, Impressum, Datenschutz)
├── router.php             # Router für PHP Built-in-Server
├── .htaccess              # Apache: Rewrites, Schutz data/content/backups
├── lib/
│   ├── bootstrap.php      # Konstanten, Autoload, Hilfsfunktionen
│   ├── Content.php        # Content laden/speichern (JSON)
│   ├── Auth.php           # Login, Session, Rate-Limit, Passwort
│   ├── Backup.php         # Backup erstellen / Restore
│   └── Media.php          # Bild-Upload, WebP/Thumb, Trash
├── templates/             # Frontend-Templates
├── assets/css/, assets/js/
├── setup/
│   └── index.php          # Setup-Wizard (einmalig)
├── admin/                 # Admin-UI (Login, Content, Medien, Backup)
├── superadmin/
│   └── index.php          # Superadmin (optional, gleicher Login mit Superadmin-Rolle)
├── content/               # JSON-Inhalte (writable)
├── data/                  # users.json, .setup.lock, Rate-Limit (writable)
├── uploads/images/        # Hochgeladene Bilder (writable)
├── uploads/trash/         # Gelöschte Bilder (writable)
└── backups/               # Backups (writable)
```

## Content-Struktur

- **content/site.json** – Startseite: Hero, Leistungen, Über uns, Kontakt, Footer (zentrale Platzhalter für Firma, Anschrift, Telefon, E-Mail, Impressum/Datenschutz-Links).
- **content/impressum.json**, **content/datenschutz.json** – Titel + HTML-Inhalt für Impressum und Datenschutz.

Platzhalterdaten (z. B. Max Mustermann) werden zentral im Footer/Startseiten-Content gepflegt und im Frontend (Footer, Impressum-Seiten) verwendet.

## Medien

- **Max. 10 MB** pro Bild; Formate: JPEG, PNG, GIF, WebP.
- Es wird automatisch versucht, **WebP** und ein **Thumbnail** zu erzeugen (PHP GD). Wenn WebP nicht verfügbar ist: Fallback auf Original, Thumbnail weiterhin best-effort.
- **Löschen** = Verschieben in den Papierkorb (`uploads/trash/`), kein sofortiges Hard-Delete.
- **Video** nur per Embed-Link (z. B. YouTube/Vimeo iframe) in den entsprechenden Content-Feldern.

## Backup & Restore

- **Automatisch:** Bei jeder Content- und Medienänderung wird ein Backup erstellt.
- **Manuell:** Im Admin unter „Backup & Restore“ → „Backup jetzt erstellen“.
- **Max. 20 Backups** – ältere werden überschrieben.
- **Restore:** Gewünschtes Backup auswählen und wiederherstellen (Content + Metadaten, Bilder). Letzter funktionierender Stand ist so wiederherstellbar.

## Sicherheit (Überblick)

- Passwörter nur als **bcrypt-Hash** in `data/users.json`.
- Session-Cookies **HttpOnly**, **SameSite=Lax**.
- Einfaches **Rate-Limit** bei Login (z. B. 5 Fehlversuche, 5 Min Sperre).
- **/admin** und **/superadmin** nur nach Login.
- **Setup** nach Installation deaktiviert (Lockfile + Zugriffsschutz).

## Superadmin (optional)

Der Setup legt nur einen **Admin** an. Ein **Superadmin** kann manuell in `data/users.json` ergänzt werden: gleiche Struktur wie `admin`, z. B.:

```json
{
  "admin": { "email": "kunde@example.com", "hash": "..." },
  "superadmin": { "email": "super@example.com", "hash": "..." }
}
```

Hash mit z. B. `password_hash('deinpasswort', PASSWORD_BCRYPT, ['cost' => 10])` erzeugen. Zugriff dann unter **/superadmin**.

## BASE-TEMPLATE

Technische Systemdokumentation zu Projektstruktur, Routing, Asset-Struktur und Brancheninstanzen: **docs/BASE-TEMPLATE.md**

## Akzeptanzkriterien (Checkliste)

Siehe **Deployment.md** am Ende für die abgehakte Checkliste.

## Lizenz

Projektintern / nach Absprache.
=======
# Modular Web Core (PHP)

Lightweight file-based web engine for modular small business web systems.

---

## Current Version

**v0.3.0 – Stable Engine + Admin Layer**

The core engine is fully functional.
Text and image content can be edited via a lightweight admin interface.
No database. No framework. Pure PHP + JSON.

---

## Philosophy

- Architecture first
- File-based modular structure
- No framework dependency
- No database requirement
- Designed for simplicity & longevity
- Built with AI-assisted development, structured by human-first system design

---

## What It Is

Modular Web Core is the architectural foundation for:

- Modular BusinessCard CMS
- Small business websites
- Upgradeable mini-CMS systems
- Downloadable web solutions for non-technical users

The goal:  
A controlled, minimal CMS alternative to WordPress – without complexity.

---

## Current Capabilities (v0.3.0)

✔ Core bootstrapping  
✔ Basic routing  
✔ Template loading system  
✔ JSON-based content management  
✔ Admin authentication (bcrypt + rate limiting)  
✔ Media upload with validation + WebP + thumbnails  
✔ Setup wizard  
✔ Config-based architecture  

---

## Architecture Overview

/core → Engine logic
/modules → Functional extensions
/templates → Frontend layouts
/public → Entry point
/config → Configuration layer

/core → Engine logic
/modules → Functional extensions
/templates → Frontend layouts
/public → Entry point
/config → Configuration layer


Full documentation:

- [Technical Guide](TECHNICAL-GUIDE.md)
- [Deployment](Deployment.md)
- [Changelog](CHANGELOG.md)
- [Roadmap](ROADMAP.md)

---

## Status

Engine stable.  
Payment automation & download layer in development.

---

Maintained by BePresent Webdesign.
