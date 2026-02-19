# Technisches Handbuch – Modular Web Core PHP

Ein Schritt-für-Schritt-Guide, damit Entwickler das Projekt ohne Cursor warten und erweitern können. Alle Angaben basieren ausschließlich auf dem aktuellen Code.

---

## A) Architektur-Überblick

### Hauptkomponenten

| Komponente | Beschreibung |
|------------|--------------|
| **Frontend** | `index.php` lädt Content aus JSON, rendert über `templates/home.php` oder `templates/page.php`. Header und Footer kommen aus `layout_header.php` / `layout_footer.php`. |
| **Routing** | Apache `.htaccess` (mod_rewrite) bzw. `router.php` für den PHP Built-in-Server. Keine zentrale Router-Klasse. |
| **Admin** | Eigene PHP-Dateien unter `/admin/`. Login, Content, Medien, Backup. Session-basiert. |
| **Upload** | `Media::upload()` in `lib/Media.php`. Endpoints: `admin/media.php` (Form), `admin/upload-api.php` (AJAX). Bilder in `uploads/img/`. |
| **Inhalte** | JSON-Dateien in `content/`: `content.json` (Startseite), `impressum.json`, `datenschutz.json`. Lade-/Speicherlogik in `lib/Content.php`. |

### Request-Flow: Was passiert bei `/impressum`?

Schrittfolge:

1. **Apache** (mit mod_rewrite): `.htaccess` fängt `/impressum` ab (wenn es keine existierende Datei ist), leitet intern um auf `index.php?page=impressum`.
2. **index.php** wird ausgeführt: `require lib/bootstrap.php`, dann `$page = $_GET['page']` → `'impressum'`.
3. `Content::getSite()` liefert `$site` und `$footer`.
4. Da `$page !== 'home'`: `Content::getImpressum()` liefert `$pageContent` aus `content/impressum.json`.
5. `$hero` wird für den Header gesetzt (Titel aus `$pageContent`, Bild aus `$site['hero']['image']`).
6. `require templates/page.php` wird aufgerufen.
7. **page.php** setzt `$title`, `$pageTitle`, `$metaDescription`, `$current` und inkludiert `layout_header.php` → `<head>`, Header, Nav, Hero mit H1.
8. Im `<main>` wird `$pageContent['content']` (HTML) ausgegeben.
9. `layout_footer.php` schließt die Seite ab.

**Hinweis (v1.x):** Keine separaten `impressum.php`/`datenschutz.php` mehr. `$pageTitle` und `$metaDescription` werden in `index.php` aus `$pageContent` bzw. `$footer` abgeleitet.

---

## B) Dateimapping (URL → Datei → Verantwortlichkeit)

| URL / Route | Datei | Verantwortlichkeit |
|-------------|-------|--------------------|
| `/` | `index.php` (via .htaccess/ router) | Startseite; lädt `home.php` |
| `/impressum` | `.htaccess` / router → `index.php?page=impressum` | Impressum; lädt `page.php`, Content aus `content/impressum.json` |
| `/datenschutz` | `.htaccess` / router → `index.php?page=datenschutz` | Datenschutz; lädt `page.php`, Content aus `content/datenschutz.json` |
| `/setup` | `setup/index.php` | Setup-Wizard (nur wenn kein `.setup.lock`) |
| `/admin` | `admin/index.php` | Admin-Übersicht; erfordert Login |
| `/admin/login.php` | `admin/login.php` | Login-Formular |
| `/admin/logout.php` | `admin/logout.php` | Abmeldung |
| `/admin/passwort.php` | `admin/passwort.php` | Passwort ändern |
| `/admin/content.php?page=site\|impressum\|datenschutz` | `admin/content.php` | Content bearbeiten |
| `/admin/media.php` | `admin/media.php` | Medien: Upload, Ersetzen, Papierkorb |
| `/admin/backup.php` | `admin/backup.php` | Backup erstellen / Restore |
| `/admin/upload-api.php` | `admin/upload-api.php` | AJAX-Upload; liefert JSON |
| `/admin/images-api.php` | `admin/images-api.php` | API: Liste aller Bilder (assets + uploads) |
| `/superadmin` | `superadmin/index.php` | Superadmin-Ansicht; erfordert Superadmin-Rolle |

---

## C) Routing-Details (.htaccess)

### Vollständige .htaccess

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

### Erklärung jeder Regel

| Regel | Wirkung |
|-------|---------|
| `RewriteRule ^(data\|content\|backups)/ - [F,L]` | Jede Anfrage auf `data/`, `content/` oder `backups/` wird mit **403 Forbidden** abgewiesen. `[F,L]` = Forbidden, Last (keine weiteren Regeln). Schützt sensible Verzeichnisse, da PHP die Dateien trotzdem lesen kann. |
| `RewriteCond %{REQUEST_FILENAME} !-f` | Nächste Regel gilt nur, wenn **keine** existierende **Datei** gefunden wird. |
| `RewriteCond %{REQUEST_FILENAME} !-d` | Nächste Regel gilt nur, wenn **kein** existierendes **Verzeichnis** gefunden wird. |
| `RewriteRule ^(impressum\|datenschutz)/?$ index.php?page=$1 [L,QSA]` | `/impressum` und `/datenschutz` (mit optionalem trailing slash) werden intern auf `index.php?page=impressum` bzw. `index.php?page=datenschutz` umgeschrieben. `QSA` = Query String Append (vorhandene Query-Parameter bleiben erhalten). |

### Fallstricke

- **Keine RewriteBase**: Absichtlich, damit die .htaccess in Subordnern funktioniert. Wenn Apache trotzdem Probleme macht, ggf. `RewriteBase /` oder `RewriteBase /projektordner/` testen.
- **mod_rewrite muss aktiv sein**: Ohne mod_rewrite werden die Rewrite-Regeln ignoriert. Auf manchem Shared Hosting ist das bereits an.
- **DirectoryIndex**: Die Startseite `/` wird oft über Apache `DirectoryIndex index.php` bedient; eine explizite Rewrite-Regel für `/` ist in der .htaccess nicht nötig.
- **Groß-/Kleinschreibung**: `/Impressum` matcht nicht, weil das Pattern `impressum` kleingeschrieben ist. Bei Bedarf `[NC]` für case-insensitive nutzen.

### Neue Seite hinzufügen (z. B. `/leistungen`)

1. **.htaccess** erweitern:

```apache
RewriteRule ^(impressum|datenschutz|leistungen)/?$ index.php?page=$1 [L,QSA]
```

2. **router.php** (falls PHP Built-in-Server genutzt wird) anpassen:

```php
if (preg_match('#^/(impressum|datenschutz|leistungen)(\.php)?$#', $uri, $m)) {
    $_GET['page'] = $m[1];
    require __DIR__ . '/index.php';
    return true;
}
```

3. **index.php**: `$allowed` erweitern und Template-Logik:

```php
$allowed = ['home', 'impressum', 'datenschutz', 'leistungen'];
// ...
} elseif ($page === 'leistungen') {
    $pageContent = Content::getPage('leistungen');  // content/leistungen.json
    $hero = ['title' => $pageContent['title'] ?? 'Leistungen', 'subtitle' => '', 'claim' => '', 'image' => $site['hero']['image'] ?? ''];
    require __DIR__ . '/templates/page.php';  // oder eigenes Template
}
```

4. **content/leistungen.json** anlegen (Titel + HTML-Content).
5. **Navigation** in `layout_header.php` um einen Link zu `leistungen.php` oder `/leistungen` erweitern.

---

## D) Template-Struktur

### Wo ist was eingebunden?

| Bereich | Datei | Zeilen (approx.) |
|---------|-------|------------------|
| `<head>`, Meta, Favicon, CSS | `templates/layout_header.php` | 7–18 |
| Navigation | `templates/layout_header.php` | 22–34 |
| Hero (H1, Hintergrundbild) | `templates/layout_header.php` | 36–46 |
| Footer | `templates/layout_footer.php` | 1–17 |
| JavaScript | `templates/layout_footer.php` | 18 |

### <head> / SEO / CSS / JS

```php
// layout_header.php (Auszug)
<link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
<title><?php echo htmlspecialchars($pageTitle ?? $title ?? 'Musterbetrieb – Service & Qualität'); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($metaDescription ?? '...'); ?>">
<link rel="canonical" href="https://<?php echo ...; ?>">
<meta property="og:title" content="...">
<meta property="og:description" content="...">
<meta property="og:type" content="website">
<meta property="og:url" content="https://...">
<link rel="stylesheet" href="assets/css/site.css?v=2">
```

```php
// layout_footer.php (Auszug)
<script src="assets/js/site.js"></script>
```

### Navigation

Definiert in `layout_header.php`. Die Variable `$current` steuert die Markierung des aktiven Menüpunktes:

```php
// layout_header.php
<a href="<?php echo $homeHref; ?>"<?php echo ($current ?? '') === 'home' ? ' class="active"' : ''; ?>>Start</a>
<a href="<?php echo $baseRef; ?>#leistungen">Leistungen</a>
<a href="<?php echo $baseRef; ?>#ueber-mich">Über mich</a>
<a href="<?php echo $baseRef; ?>#kontakt">Kontakt</a>
<a href="<?php echo htmlspecialchars(base_url('impressum')); ?>"<?php echo ($current ?? '') === 'impressum' ? ' class="active"' : ''; ?>>Impressum</a>
<a href="<?php echo htmlspecialchars(base_url('datenschutz')); ?>"<?php echo ($current ?? '') === 'datenschutz' ? ' class="active"' : ''; ?>>Datenschutz</a>
```

`$baseRef` und `$homeHref` werden am Anfang von `layout_header.php` aus `$current` berechnet (Startseite vs. Unterseiten).

### Hero: H1 dynamisch oder statisch?

**Dynamisch.** Das H1 kommt aus `$hero['title']`:

```php
// layout_header.php (Zeile 40)
<h1 class="header-hero-title"><?php echo htmlspecialchars($hero['title'] ?? 'Willkommen'); ?></h1>
```

- **Startseite**: `$hero` aus `Content::getSite()` → `content.json` (`hero.hero_title`).
- **Impressum/Datenschutz**: `$hero['title']` aus `$pageContent['title']` (impressum.json, datenschutz.json).

Der Hero-Bereich wird nur gerendert, wenn `isset($hero) && !empty($hero['title'])`. Das Hintergrundbild ist `$hero['image']` oder Fallback `assets/img/hero.webp`.

### Wo werden Inhalte geladen?

| Quelle | Methode | Datei |
|--------|---------|-------|
| Startseite (Hero, Leistungen, Über uns, Kontakt, Footer) | `Content::getSite()` | `lib/Content.php` |
| Impressum | `Content::getImpressum()` → `Content::getPage('impressum')` | `lib/Content.php` |
| Datenschutz | `Content::getDatenschutz()` → `Content::getPage('datenschutz')` | `lib/Content.php` |
| Rohdaten (admin) | `Content::getAll()` | `lib/Content.php` |

`Content::getPage()` liest `content/{name}.json` und liefert `title` + `content` (HTML).

---

## E) SEO-Core

### Wie werden $pageTitle und $metaDescription gesetzt?

| Kontext | Wo | Logik |
|---------|-----|-------|
| **Startseite** | `templates/home.php` | `$title = $hero['title'] ?? 'Willkommen'`, `$pageTitle = $pageTitle ?? $title`, `$metaDescription = $metaDescription ?? 'Ihr zuverlässiger Partner...'` |
| **Impressum/Datenschutz (via index.php)** | `index.php` + `templates/page.php` | `index.php` setzt `$pageTitle` aus `$pageContent['title']`, `$metaDescription` aus Firmenname; `page.php` nutzt `$pageTitle ?? $title`, `$metaDescription ?? ''` |

### Standardwerte vs. seitenspezifisch

- **Standard** (wenn nichts gesetzt): `$pageTitle ?? $title ?? 'Musterbetrieb – Service & Qualität'`, `$metaDescription ?? 'Ihr zuverlässiger Partner...'`.
- **Seitenspezifisch**: Jedes Template setzt `$title` und optional `$pageTitle`/`$metaDescription` vor dem Include von `layout_header.php`. Für Impressum/Datenschutz übernimmt `index.php` die SEO-Variablen.

### Canonical / OG: wo generiert, worauf achten

Generiert in `layout_header.php`:

```php
<link rel="canonical" href="https://<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?>">
<meta property="og:url" content="https://<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?>">
```

**Wichtig:**

- Es wird fest **https** verwendet. Bei HTTP-only Installationen kann das zu fehlerhaften Canonical-/OG-URLs führen → ggf. `$_SERVER['REQUEST_SCHEME']` oder `$_SERVER['HTTPS']` nutzen.
- `HTTP_HOST` und `REQUEST_URI` können theoretisch manipuliert werden; für SEO sind sie in der Regel vertrauenswürdig, aber für sicherheitskritische Logik sollte man vorsichtig sein.
- Bei Subdomains oder Staging: `HTTP_HOST` reflektiert die aktuelle Domain, das ist gewollt.

---

## F) Upload-Feature

### Upload-Endpunkte

| Endpunkt | Methode | Nutzung |
|----------|---------|---------|
| `admin/media.php` | POST (Form) | Klassischer Upload, Ersetzen, Papierkorb |
| `admin/upload-api.php` | POST (multipart) | AJAX-Upload aus dem Content-Editor; Antwort: `{ok, path, name}` oder `{error}` |

### Zielordner + Dateinamen-Strategie

- **Ordner**: `uploads/img/`
- **Dateiname**: `{slot}_YYYYMMDD_HHMMSS_{uniqid6}.{ext}` (z. B. `hero_image_20260216_150624_0c5577.jpg`)
- **Trash**: Gelöschte Bilder → `uploads/trash/` (kein Hard-Delete)
- **Zusätzlich**: `Media::makeWebPAndThumb()` erzeugt `.webp` und `_thumb.jpg` im selben Ordner

```php
// lib/Media.php (Auszug)
$base = $slot ? preg_replace('/[^a-z0-9_-]/', '', $slot) . '_' : '';
$name = $base . date('Ymd_His') . '_' . substr(uniqid(), -6) . '.' . $ext;
$dir = UPLOADS_DIR . '/img';
```

### Sicherheit

| Maßnahme | Umsetzung |
|----------|-----------|
| Zugriffsschutz | `Auth::requireAdmin()` in `upload-api.php` und `media.php` |
| CSRF | `csrf_verify()` vor jedem Upload |
| Größenlimit | 10 MB (`Media::MAX_SIZE`) |
| Erlaubte Typen | Nur `image/jpeg`, `image/png`, `image/webp` (MIME per `finfo_file`) |
| Dateinamen | Kein `../`, kein absoluter Pfad; `normalize_image_path()` in Content prüft erlaubte Präfixe |

```php
// lib/Media.php (Auszug)
private const MAX_SIZE = 10 * 1024 * 1024; // 10MB
private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
if (!in_array($mime, self::ALLOWED_TYPES, true)) {
    return ['error' => 'Nur JPG, PNG, WebP erlaubt. Keine SVG oder ausführbaren Dateien.'];
}
```

### Bilder im Frontend auswählen / anzeigen

- **Content-Editor** (`admin/content.php`): Vier feste Slots (hero_image, details_image, portrait_image, contact_image). Pro Slot:
  - Dropzone zum Hochladen (AJAX an `upload-api.php`)
  - Button „Vorhandenes Bild wählen“ → Modal mit Tabs „assets/img“ und „uploads/img“, Daten aus `images-api.php`
- **Medien-Übersicht** (`admin/media.php`): Liste aller Bilder aus `uploads/img/`, Pfad zum Kopieren, Ersetzen und In-Papierkorb-verschieben.
- **Frontend**: Pfade aus `content.json` → `asset_url()` für subfolder-taugliche URLs.

---

## G) Admin / Auth

### Session-Handling

```php
// lib/Auth.php (Auszug)
public static function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (is_dir(DATA_DIR) && is_writable(DATA_DIR)) {
            @session_save_path(DATA_DIR);
        }
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}
```

- **Speicherort**: `data/` (Session-Dateien)
- **Cookie**: HttpOnly, SameSite=Lax
- Nach Login: `$_SESSION['user'] = ['email' => ..., 'superadmin' => bool]`

### Login / Logout

- **Login**: `Auth::login($email, $password)` → prüft `data/users.json`, bcrypt, setzt `$_SESSION['user']`, leitet auf `admin/` weiter.
- **Logout**: `Auth::logout()` → Session leeren, Cookie löschen, `session_destroy()`.

### Rollen

| Rolle | Speicherort | Zugriff |
|-------|-------------|---------|
| **admin** | `data/users.json` unter Schlüssel `admin` | Admin-Bereich (Content, Medien, Backup, Passwort) |
| **superadmin** | `data/users.json` unter Schlüssel `superadmin` | Wie admin plus `/superadmin` |

```json
// data/users.json (Beispiel)
{
  "admin": { "email": "kunde@example.com", "hash": "$2y$10$..." },
  "superadmin": { "email": "super@example.com", "hash": "$2y$10$..." }
}
```

Superadmin kann manuell ergänzt werden (Hash z. B. mit `password_hash('passwort', PASSWORD_BCRYPT, ['cost' => 10])`).

### Benutzer speichern

- **Datei**: `data/users.json`
- **Setup** erstellt nur `admin`; `superadmin` ist optional und manuell.

### Rate-Limit

- 5 Fehlversuche → 5 Minuten Sperre
- Zähler in `data/.rate_login` (JSON mit `time` und `count`)

---

## H) How to Extend

### Neue Seite hinzufügen (Routing + Template + Content)

1. **.htaccess** und **router.php** um die neue Route ergänzen (siehe Abschnitt C).
2. **index.php**: `$allowed` erweitern, Logik für `$page === 'neueseite'` ergänzen (Content laden, `$hero`, `require` Template).
3. **content/neueseite.json** anlegen: `{"title": "...", "content": "<p>...</p>"}`.
4. **Template**: Entweder `templates/page.php` wiederverwenden oder neues Template `templates/neueseite.php`.
5. **Navigation** in `layout_header.php` um einen Link ergänzen.
6. **Admin** (optional): In `admin/content.php` `$allowed` um `'neueseite'` erweitern und entsprechende Bearbeitungslogik hinzufügen.

### Neues Bildset einbauen

1. **content.json** / Content-Klasse: Neuen Slot unter `images` hinzufügen (z. B. `gallery_image`).
2. **Content::getSite()** / `mergeDefaults()`: Default für den neuen Slot setzen.
3. **admin/content.php**: Im Formular einen weiteren `img-slot` mit `data-slot="gallery_image"` und passendem `img_*`-Name ergänzen.
4. **Frontend-Template**: Slot nutzen (z. B. `$site['images']['gallery_image']`) und mit `asset_url()` ausgeben.
5. **normalize_image_path**: Akzeptiert bereits `uploads/(img|images)/` und `assets/img/`, keine Änderung nötig, solange die Pfade diesem Muster folgen.

### Neues Kundenprojekt aus Base erstellen

1. Gesamten Projektordner kopieren (z. B. `modular-web-core-php_v1_base` → `modular-web-core-php_v1_kunde`).
2. **content/** anpassen: `content.json`, `impressum.json`, `datenschutz.json`.
3. **assets/img/** anpassen: Hero, Detail, Portrait, Kontakt, Favicon ersetzen.
4. **uploads/img/**, **uploads/trash/**, **backups/** leeren.
5. Optional: `data/.setup.lock` entfernen und `/setup` erneut ausführen, um neue Zugangsdaten zu setzen.
6. `.htaccess` kann unverändert bleiben (subfolder-tauglich, keine RewriteBase).

### Deployment auf All-Inkl / Shared Hosting: typische Stolperfallen

| Problem | Lösung |
|--------|--------|
| **index.htm hat Vorrang** | `index.php` als DirectoryIndex setzen oder `index.htm` umbenennen/löschen, falls vorhanden. |
| **403 Forbidden** | Schreibrechte für `data/`, `content/`, `uploads/`, `backups/` prüfen (755 oder 775). Besitzer sollte der Webserver-User sein. |
| **.htaccess wird ignoriert** | `AllowOverride All` muss für das Verzeichnis gesetzt sein. Bei All-Inkl oft im Kundenbereich konfigurierbar. |
| **mod_rewrite fehlt** | Viele Shared-Hoster haben es aktiv. Wenn nicht: Nginx-Konfiguration nutzen oder Support anfragen. |
| **500 Internal Server Error** | PHP-Fehler: Temporär `display_errors = 1` in `bootstrap.php` setzen. Rechte prüfen. Hoster-Logs prüfen. |
| **Subfolder-Probleme** | `base_url()` und `asset_url()` berechnen den Pfad dynamisch. Bei falschen Asset-URLs: `DOCUMENT_ROOT` und `SCRIPT_NAME` prüfen. |
| **HTTPS-Canonical/OG** | In `layout_header.php` wird fest `https://` verwendet. Bei reinem HTTP ggf. `$_SERVER['REQUEST_SCHEME']` oder `$_SERVER['HTTPS']` einbeziehen. |

---

## I) Code-Auszüge mit Erklärung

### 1. index.php – Frontend-Einstieg

```php
$page = $_GET['page'] ?? 'home';
$allowed = ['home', 'impressum', 'datenschutz'];
if (!in_array($page, $allowed, true)) {
    $page = 'home';
}

$site = Content::getSite();
$footer = $site['footer'] ?? [];

if ($page === 'home') {
    $hero = $site['hero'] ?? [];
    $features = $site['features'] ?? [];
    $about = $site['about'] ?? [];
    $contact = $site['contact'] ?? [];
    require __DIR__ . '/templates/home.php';
} else {
    $pageContent = $page === 'impressum' ? Content::getImpressum() : Content::getDatenschutz();
    $hero = [
        'title' => $pageContent['title'] ?? ($page === 'impressum' ? 'Impressum' : 'Datenschutz'),
        'subtitle' => '', 'claim' => '',
        'image' => $site['hero']['image'] ?? '',
    ];
    require __DIR__ . '/templates/page.php';
}
```

**Erklärung:** Die Seite wird aus `$_GET['page']` ermittelt und gegen `$allowed` abgesichert. Für die Startseite werden Hero, Leistungen, Über-uns und Kontakt aus `$site` geladen und `home.php` eingebunden. Für Impressum/Datenschutz wird `getImpressum()`/`getDatenschutz()` aufgerufen, ein vereinfachtes `$hero` gebaut und `page.php` genutzt.

---

### 2. base_url() – Subfolder-taugliche Basis-URL

```php
function base_url(string $path = ''): string {
    $projectRoot = realpath(__DIR__ . '/..');
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

    if (!$projectRoot || !$docRoot || strpos($projectRoot, $docRoot) !== 0) {
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/';
        $scriptDir = preg_replace('#/(admin|setup)/$#', '/', $scriptDir);
        $base = $scriptDir;
    } else {
        $base = str_replace('\\', '/', substr($projectRoot, strlen($docRoot)));
        $base = '/' . ltrim($base, '/');
        $base = rtrim($base, '/') . '/';
    }
    return $base . ltrim($path, '/');
}
```

**Erklärung:** Wenn das Projekt nicht unter dem Document Root liegt, wird aus `SCRIPT_NAME` der Basis-Pfad abgeleitet und bei `/admin/` oder `/setup/` eine Ebene nach oben gegangen. So funktionieren Links auch in Unterordnern (z. B. `/meinprojekt/`).

---

### 3. Auth::requireAdmin() – Zugriffsschutz

```php
public static function requireAdmin(): void {
    if (!self::isLoggedIn()) {
        header('Location: ' . base_url('admin/login.php'));
        exit;
    }
}
```

**Erklärung:** Jede geschützte Admin-Seite ruft `Auth::requireAdmin()` auf. Ist der Nutzer nicht eingeloggt, erfolgt ein Redirect zur Login-Seite.

---

### 4. Media::upload() – Kernteil des Uploads

```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
if (!in_array($mime, self::ALLOWED_TYPES, true)) {
    return ['error' => 'Nur JPG, PNG, WebP erlaubt.'];
}
$name = $base . date('Ymd_His') . '_' . substr(uniqid(), -6) . '.' . $ext;
$path = UPLOADS_DIR . '/img' . '/' . $name;
move_uploaded_file($file['tmp_name'], $path);
self::makeWebPAndThumb($path, $name);
return ['ok' => true, 'path' => 'uploads/img/' . $name, 'name' => $name];
```

**Erklärung:** Der MIME-Typ wird mit `finfo` geprüft, der Dateiname generiert (Slot + Datum + Zufall), die Datei nach `uploads/img/` verschoben und optional WebP sowie Thumbnail erzeugt. Der Rückgabepfad ist relativ zum Projektroot.

---

### 5. normalize_image_path() – Sichere Bildpfade

```php
function normalize_image_path(string $path): string {
    $path = trim(str_replace('\\', '/', $path));
    if (preg_match('#^[a-z]:#i', $path) || strpos($path, '../') !== false) return '';
    if (preg_match('#^(uploads/(img|images)/|assets/img/)#', $path)) return $path;
    if (strpos($path, '/') === false && preg_match('/\.(jpe?g|png|webp)$/i', $path))
        return 'assets/img/' . $path;
    return '';
}
```

**Erklärung:** Erlaubt sind nur Pfade, die mit `uploads/img/`, `uploads/images/` oder `assets/img/` beginnen, oder reine Dateinamen mit erlaubter Endung. Absolute Pfade und `../` werden abgelehnt.
