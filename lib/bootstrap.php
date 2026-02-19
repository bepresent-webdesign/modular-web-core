<?php
/**
 * Universal Core Mini-CMS – Bootstrap
 * Shared webhosting, file-based, no DB.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

define('CORE_ROOT', dirname(__DIR__));
define('DATA_DIR', CORE_ROOT . '/data');
define('CONTENT_DIR', CORE_ROOT . '/content');
define('UPLOADS_DIR', CORE_ROOT . '/uploads');
define('BACKUPS_DIR', CORE_ROOT . '/backups');
define('SETUP_LOCK_FILE', DATA_DIR . '/.setup.lock');

require_once CORE_ROOT . '/lib/Content.php';
require_once CORE_ROOT . '/lib/Auth.php';
require_once CORE_ROOT . '/lib/Backup.php';
require_once CORE_ROOT . '/lib/Media.php';
require_once CORE_ROOT . '/lib/HtmlSanitizer.php';

function ensure_dirs(): void {
    $dirs = [DATA_DIR, CONTENT_DIR, UPLOADS_DIR, UPLOADS_DIR . '/img', UPLOADS_DIR . '/images', UPLOADS_DIR . '/trash', BACKUPS_DIR];
    foreach ($dirs as $d) {
        if (!is_dir($d)) {
            mkdir($d, 0755, true);
        }
    }
}

function is_setup_locked(): bool {
    return file_exists(SETUP_LOCK_FILE);
}

function json_read(string $path): array {
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_write(string $path, array $data): bool {
    ensure_dirs();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

/** CSRF-Token für POST-Requests */
function csrf_token(): string {
    Auth::startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Gibt Body-Text sicher aus (für Admin-formatierte Texte: b, i, u, span).
 * Bei HTML: sanitized ausgeben, bei Plain-Text: nl2br(htmlspecialchars).
 * @param bool $legal Für Impressum/Datenschutz – erlaubt h2, h3, p, ul, li, a
 */
function output_body_text(string $content, bool $legal = false): void {
    if ($content === '') return;
    if (HtmlSanitizer::containsHtml($content)) {
        echo $legal ? HtmlSanitizer::sanitizeLegal($content) : HtmlSanitizer::sanitize($content);
    } else {
        echo nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
    }
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): bool {
    Auth::startSession();
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Normalisiert und validiert Bildpfade. Nur server-relative Pfade erlaubt.
 * Erlaubt: uploads/img/xxx, assets/img/xxx. Reine Dateinamen → assets/img/xxx.
 * Lokale/absolute Pfade werden verworfen.
 */
function normalize_image_path(string $path): string {
    $path = trim(str_replace('\\', '/', $path));
    $path = preg_replace('#^/+#', '', $path);
    if ($path === '') return '';
    if (preg_match('#^[a-z]:#i', $path) || strpos($path, '../') !== false) return '';
    if (preg_match('#^(uploads/(img|images)/|assets/img/)#', $path)) return $path;
    if (strpos($path, '/') === false && preg_match('/\.(jpe?g|png|webp)$/i', $path)) return 'assets/img/' . $path;
    return $path;
}

/**
 * Gibt eine für das aktuelle Projekt (inkl. Subdirectory) gültige Asset-URL zurück.
 * Vermeidet 404 bei Bildpfaden in /uploads/ oder assets/img/.
 */
function asset_url(string $path): string {
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    if ($path[0] === '/') $path = ltrim($path, '/');
    elseif (strpos($path, '/') === false && preg_match('/\.(jpe?g|png|webp)$/i', $path)) {
        $path = 'assets/img/' . $path;
    }
    return base_url($path);
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        // Projektroot = eine Ebene über /lib
        $projectRoot = realpath(__DIR__ . '/..');
        $docRoot     = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

        // Fallback, falls DOCUMENT_ROOT nicht sauber passt
        if (!$projectRoot || !$docRoot || strpos($projectRoot, $docRoot) !== 0) {
            // SCRIPT_NAME zeigt auf /modular-web-core-php_v1_handwerker_TEST/admin/login.php
            // wir nehmen den Ordner-Teil und gehen (bei admin/setup) eine Ebene hoch
            $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/';

            // Wenn wir im /admin/ oder /setup/ sind, eine Ebene hoch:
            $scriptDir = preg_replace('#/(admin|setup)/$#', '/', $scriptDir);

            $base = $scriptDir;
        } else {
            // Webpfad = Projektroot minus DocumentRoot
            $base = str_replace('\\', '/', substr($projectRoot, strlen($docRoot)));
            $base = '/' . ltrim($base, '/');
            $base = rtrim($base, '/') . '/';
        }

        return $base . ltrim($path, '/');
    }
}

/**
 * URL für Impressum/Datenschutz – funktioniert ohne Rewrite (Fallback: index.php?page=…).
 * Nutzt index.php?page=X, damit Links immer funktionieren (MAMP, Apache, Subfolder).
 * base_url() wird korrekt angewendet.
 */
function page_url(string $page): string {
    $allowed = ['impressum', 'datenschutz'];
    if (!in_array($page, $allowed, true)) return base_url();
    return rtrim(base_url('index.php'), '/') . '?page=' . $page;
}