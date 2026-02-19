# BASE-TEMPLATE – Systemdokumentation

## Modulares Mini-CMS – Technische Grundlagen

Dieses Dokument beschreibt die technische Struktur des modularen Mini-CMS. Es dient als Referenz für die Wartung, Erweiterung und die Erstellung neuer Brancheninstanzen. **Es werden ausschließlich tatsächlich im Code vorhandene Strukturen dokumentiert.**

---

## 1. Projektziel / Zweck

File-basiertes Mini-CMS für Shared Webhosting (IONOS, Strato, 1&1, all-inkl etc.). Kein Node, keine MySQL – nur PHP und JSON. Nach FTP-Upload wird die Installation im Browser unter `/setup` durchgeführt, danach werden Inhalte unter `/admin` gepflegt. Geeignet für Visitenkarten-/Handwerker-Websites mit Startseite, Impressum und Datenschutz.

---

## 2. Ordnerstruktur (Tree-Ansicht)

```
├── index.php              # Frontend-Einstieg (Startseite, Impressum, Datenschutz)
├── router.php             # Router für PHP Built-in-Server (php -S localhost:8080 router.php)
├── .htaccess              # Apache: URL-Rewrites, Schutz sensibler Verzeichnisse
├── Deployment.md          # Deployment-Anleitung (FTP, Rechte, Nginx)
├── README.md
│
├── lib/
│   ├── bootstrap.php      # Konstanten, Autoload, Hilfsfunktionen (base_url, asset_url, csrf_*, normalize_image_path)
│   ├── Content.php        # JSON-Content laden/speichern (content.json, impressum.json, datenschutz.json)
│   ├── Auth.php           # Login, Session, Rate-Limit, Passwort ändern
│   ├── Backup.php         # Backup erstellen / Restore
│   └── Media.php          # Bild-Upload, WebP/Thumb, Trash
│
├── templates/
│   ├── layout_header.php  # Zentraler <head>, SEO-Tags, Header mit Nav, Hero
│   ├── layout_footer.php  # Footer und Scripts
│   ├── home.php           # Startseiten-Template (Leistungen, Über uns, Kontakt)
│   └── page.php           # Template für Impressum/Datenschutz
│
├── assets/
│   ├── css/               # site.css, admin.css
│   ├── js/                # site.js
│   └── img/               # Statische Bilder (Hero-Platzhalter, favicon.ico)
│
├── setup/
│   └── index.php          # Einmaliger Setup-Wizard
│
├── admin/
│   ├── index.php          # Admin-Übersicht (Links zu Content)
│   ├── login.php          # Login-Formular
│   ├── logout.php         # Abmeldung
│   ├── passwort.php       # Passwort ändern
│   ├── content.php        # Content bearbeiten (Startseite, Impressum, Datenschutz)
│   ├── media.php          # Medienverwaltung (Upload, Ersetzen, Papierkorb)
│   ├── backup.php         # Backup & Restore
│   ├── upload-api.php     # AJAX-Bild-Upload (JSON-Antwort)
│   └── images-api.php     # API: Liste aller Bilder (assets + uploads)
│
├── superadmin/
│   └── index.php          # Superadmin (optional, manuell in users.json)
│
├── content/               # JSON-Inhalte (writable)
│   ├── content.json       # Startseite: Hero, Leistungen, Über uns, Kontakt, Footer, Bilder
│   ├── impressum.json     # Impressum: title, content (HTML)
│   ├── datenschutz.json   # Datenschutz: title, content (HTML)
│   └── .htaccess          # Require all denied
│
├── data/                  # Sensible Daten (writable)
│   ├── users.json         # Admin/Superadmin (email, bcrypt-hash)
│   ├── .setup.lock        # Sperrt Setup nach Installation
│   ├── .rate_login        # Rate-Limit bei Login (5 Fehlversuche, 5 Min)
│   └── .htaccess          # Require all denied
│
├── uploads/
│   ├── img/               # Hochgeladene Bilder
│   ├── images/            # (ensure_dirs erstellt beide)
│   └── trash/             # Gelöschte Bilder (Papierkorb)
│
├── backups/               # Automatische und manuelle Backups
│
└── docs/
    └── BASE-TEMPLATE.md   # Diese Dokumentation
```

---

## 3. Routing (.htaccess)

### Apache (.htaccess)

```apache
# Universal Core Mini-CMS (neutral, copy-safe)
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Security: block direct access to sensitive dirs (PHP reads by path)
    RewriteRule ^(data|content|backups)/ - [F,L]

    # Frontend routes: /impressum, /datenschutz
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(impressum|datenschutz)/?$ index.php?page=$1 [L,QSA]
</IfModule>
```

**Erklärung:**

- **Modul**: `mod_rewrite` erforderlich
- **RewriteBase**: Nicht gesetzt – die `.htaccess` ist kopierfähig und funktioniert in beliebigen Unterordnern
- **Schutz**: Direkte Anfragen an `data/`, `content/`, `backups/` werden mit **403** blockiert
- **Frontend-Routen**: `/impressum` und `/datenschutz` werden auf `index.php?page=impressum` bzw. `index.php?page=datenschutz` umgeleitet
- **Statische Dateien**: Bestehende Dateien und Verzeichnisse werden unverändert ausgeliefert (`-f` / `-d`)
- **Startseite**: `/` wird standardmäßig von Apache an `index.php` weitergeleitet (oder `index.php` direkt), Router setzt `page=home`

### PHP Built-in-Server (router.php)

```php
// Serve existing files (assets, uploads) as-is
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

// Frontend: /, /impressum, /datenschutz → index.php
// Setup: /setup, /setup/
// Admin: /admin, /admin/login.php, content.php, media.php, backup.php, upload-api.php, images-api.php, passwort.php, logout.php
// Superadmin: /superadmin, /superadmin/
// Sonst: 404
```

---

## 4. Seitenaufbau (Header / Nav / Hero / Footer)

### Welche Datei rendert was

| Bereich | Datei | Beschreibung |
|---------|-------|--------------|
| `<head>`, SEO, Favicon, CSS | `templates/layout_header.php` | Meta-Tags, Title, Canonical, OG, favicon.ico, site.css |
| Header-Bar, Logo, Navigation | `templates/layout_header.php` | Logo aus `footer['company_name']`, Nav-Links (Start, Leistungen, Über mich, Kontakt, Impressum, Datenschutz) |
| Hero (mit Hintergrundbild) | `templates/layout_header.php` | Wenn `$hero` mit `title` gesetzt: Hintergrundbild, Titel, Subtitle, Claim. Bild: `$hero['image']` oder Fallback `assets/img/hero.webp` |
| Hauptinhalt Startseite | `templates/home.php` | Leistungen, Über uns, Kontakt – inkl. Bilder aus `site['images']` |
| Hauptinhalt Impressum/Datenschutz | `templates/page.php` | HTML aus `$pageContent['content']` |
| Footer | `templates/layout_footer.php` | Firma, Adresse, Telefon, E-Mail, Öffnungszeiten, Links Impressum/Datenschutz |
| Scripts | `templates/layout_footer.php` | `assets/js/site.js` |

### Ablauf im Frontend (index.php)

1. `$page = $_GET['page'] ?? 'home'` (erlaubt: home, impressum, datenschutz)
2. `Content::getSite()` → `$site`, `$footer`
3. **home**: `$hero`, `$features`, `$about`, `$contact` aus `$site` → `templates/home.php`
4. **impressum/datenschutz**: `Content::getImpressum()` / `Content::getDatenschutz()` → `$pageContent`, `$hero` für Header → `templates/page.php`

### Impressum/Datenschutz (v1.x)

Keine separaten PHP-Dateien mehr. `index.php` setzt `$pageTitle` und `$metaDescription` aus `$pageContent` bzw. `$footer['company_name']`. Aufruf ausschließlich über `/impressum` und `/datenschutz` (Routing via .htaccess bzw. router.php).

---

## 5. Admin / Login

### Einstiegspunkte

| URL | Datei | Beschreibung |
|-----|-------|--------------|
| `/admin` | `admin/index.php` | Admin-Übersicht (nur nach Login) |
| `/admin/login.php` | `admin/login.php` | Login-Formular (E-Mail, Passwort) |
| `/admin/logout.php` | `admin/logout.php` | Abmeldung |
| `/admin/passwort.php` | `admin/passwort.php` | Passwort ändern |
| `/admin/content.php?page=site\|impressum\|datenschutz` | `admin/content.php` | Content bearbeiten |
| `/admin/media.php` | `admin/media.php` | Medien (Upload, Ersetzen, Papierkorb) |
| `/admin/backup.php` | `admin/backup.php` | Backup & Restore |
| `/superadmin` | `superadmin/index.php` | Superadmin (nur mit Superadmin-Rolle) |

### Session

- **Speicherort**: `data/` (über `session_save_path(DATA_DIR)`)
- **Start**: `Auth::startSession()` in Login, Content, Media, Backup etc.
- **Cookie**: HttpOnly, SameSite=Lax
- **Nach Login**: `$_SESSION['user'] = ['email' => ..., 'superadmin' => bool]`

### Rollen

- **admin**: Wird im Setup angelegt. Zugriff auf Admin (Content, Medien, Backup, Passwort).
- **superadmin**: Manuell in `data/users.json` ergänzbar. Gleiche Struktur wie admin, zusätzlich Zugriff auf `/superadmin`. `Auth::isSuperadmin()` prüft `$_SESSION['user']['superadmin']`.

### Zugriffsschutz

- `Auth::requireAdmin()`: Redirect nach `admin/login.php` wenn nicht eingeloggt
- `Auth::requireSuperadmin()`: Redirect nach `admin/` wenn kein Superadmin

### Rate-Limit

- 5 Fehlversuche → 5 Minuten Sperre (`.rate_login` in `data/`)

### Setup

- **URL**: `/setup`
- **Datei**: `setup/index.php`
- Nach Abschluss: `data/.setup.lock` wird angelegt, Setup antwortet mit 403

---

## 6. Upload-Funktion

### Wo gespeichert

- **Pfad**: `uploads/img/`
- **Dateiname**: `{slot}_YYYYMMDD_HHMMSS_{uniqid}.{ext}` (z. B. `hero_image_20260216_150624_0c5577.webp`)
- **Trash**: Gelöschte Bilder → `uploads/trash/` (kein Hard-Delete)

### Wie referenziert

- **Im Content**: Als relativer Pfad in `content.json` unter `images` (z. B. `uploads/img/portrait_image_20260216_150703_767001.webp`)
- **Im Frontend**: `asset_url($path)` in `lib/bootstrap.php` erzeugt subfolder-taugliche URL
- **Erlaubte Pfade**: `uploads/img/`, `uploads/images/`, `assets/img/` (via `normalize_image_path`)

### Upload-Endpunkte

- **Form-Upload**: `admin/media.php` (POST, action=upload)
- **AJAX-Upload**: `admin/upload-api.php` (POST, liefert JSON `{ok, path, name}` oder `{error}`)
- **Bilderliste**: `admin/images-api.php` (JSON: `assets`, `uploads`)

### Regeln (lib/Media.php)

- Max. 10 MB pro Bild
- Formate: JPEG, PNG, WebP (kein GIF laut Code, Deployment erwähnt GIF)
- Automatisch: WebP-Variante und Thumbnail (PHP GD), falls verfügbar

---

## 7. SEO-Basis

### Wo gesetzt

| Tag | Datei | Variable |
|-----|-------|----------|
| `<title>` | `templates/layout_header.php` | `$pageTitle ?? $title ?? 'Musterbetrieb – Service & Qualität'` |
| `<meta name="description">` | `templates/layout_header.php` | `$metaDescription ?? 'Ihr zuverlässiger Partner...'` |
| `<link rel="canonical">` | `templates/layout_header.php` | `https://{HOST}{REQUEST_URI}` |
| `<meta property="og:title">` | `templates/layout_header.php` | `$pageTitle ?? $title ?? 'Musterbetrieb'` |
| `<meta property="og:description">` | `templates/layout_header.php` | `$metaDescription ?? ''` |
| `<meta property="og:type">` | `templates/layout_header.php` | `website` |
| `<meta property="og:url">` | `templates/layout_header.php` | `https://{HOST}{REQUEST_URI}` |

### Pro Seite überschrieben

| Seite | Datei | Überschreibung |
|-------|-------|----------------|
| Startseite | `templates/home.php` | `$title` aus `$hero['title']`, `$pageTitle ?? $title`, `$metaDescription` (Default) |
| Impressum/Datenschutz | `index.php` + `templates/page.php` | `index.php` setzt `$pageTitle` aus `$pageContent['title']`, `$metaDescription` aus Firmenname; `page.php` nutzt diese Variablen |

---

## 8. Favicon

- **Pfad**: `assets/img/favicon.ico`
- **Einfügen**: In `templates/layout_header.php` Zeile 10:
  ```html
  <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
  ```
- **Hinweis**: Relativer Pfad (dokumentenrelativ). Die Datei muss manuell in `assets/img/` abgelegt werden.

---

## 9. Deployment

### Methode

- **FTP**: Alle Projektdateien per FTP in Webroot (z. B. `htdocs`, `www`, `public_html`) hochladen
- **Kein Composer, kein npm, keine Datenbank**

### Schreibrechte (wichtig bei 403/500)

| Ordner | Zweck | Typische Rechte |
|--------|-------|------------------|
| `data/` | users.json, .setup.lock, Rate-Limit, Sessions | 755 oder 775 |
| `content/` | content.json, impressum.json, datenschutz.json | 755 oder 775 |
| `uploads/` | Bilder | 755 oder 775 |
| `uploads/img/` | Hochgeladene Bilder | 755 oder 775 |
| `uploads/trash/` | Gelöschte Bilder | 755 oder 775 |
| `backups/` | Backup-Dateien | 755 oder 775 |

### Ablauf nach Upload

1. Browser: `https://ihre-domain.tld/setup`
2. Admin-E-Mail und Passwort (min. 8 Zeichen) eingeben, Installation starten
3. Setup legt an: `data/users.json`, `data/.setup.lock`, `content/*.json`, benötigte Ordner
4. Danach: Website unter `/`, Admin unter `/admin`

Details und Fehlerbehebung: **Deployment.md**

---

## 10. Brancheninstanz erstellen

1. **Projektordner kopieren** (z. B. `modular-web-core-php_v1_base` → `modular-web-core-php_v1_kunde`)
2. **Inhalte anpassen**: `content/content.json`, `content/impressum.json`, `content/datenschutz.json`
3. **Assets austauschen**: Bilder in `assets/img/`, Favicon `assets/img/favicon.ico`
4. **Uploads und Backups leeren**: `uploads/img/`, `uploads/images/`, `uploads/trash/`, `backups/`
5. **Setup erneut**: `data/.setup.lock` entfernen, dann `/setup` aufrufen (optional)
6. **Relative Pfade**: .htaccess und Asset-Pfade sind subfolder-tauglich, keine RewriteBase nötig
