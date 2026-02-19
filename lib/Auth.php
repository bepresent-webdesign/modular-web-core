<?php
declare(strict_types=1);

/**
 * Auth: login, session, rate limit. Bcrypt, HttpOnly/SameSite.
 */
class Auth {
    private const USERS_FILE = DATA_DIR . '/users.json';
    private const RATE_FILE = DATA_DIR . '/.rate_login';
    private const RATE_MAX_ATTEMPTS = 5;
    private const RATE_WINDOW = 300; // 5 min

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

    public static function isLoggedIn(): bool {
        self::startSession();
        return !empty($_SESSION['user']) && !empty($_SESSION['user']['email']);
    }

    public static function isSuperadmin(): bool {
        return self::isLoggedIn() && !empty($_SESSION['user']['superadmin']);
    }

    public static function getUser(): ?array {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    public static function login(string $email, string $password): array {
        $email = trim(strtolower($email));
        if ($email === '' || $password === '') {
            return ['error' => 'E-Mail und Passwort eingeben.'];
        }
        if (self::isRateLimited()) {
            return ['error' => 'Zu viele Fehlversuche. Bitte später erneut versuchen.'];
        }
        $users = json_read(self::USERS_FILE);
        $user = null;
        $role = null;
        foreach (['admin', 'superadmin'] as $r) {
            if (isset($users[$r]) && strtolower($users[$r]['email'] ?? '') === $email) {
                $user = $users[$r];
                $role = $r;
                break;
            }
        }
        if (!$user || !password_verify($password, $user['hash'] ?? '')) {
            self::recordFailedAttempt();
            return ['error' => 'Ungültige Anmeldedaten.'];
        }
        self::clearRateLimit();
        self::startSession();
        $_SESSION['user'] = [
            'email' => $user['email'],
            'superadmin' => $role === 'superadmin',
        ];
        return ['ok' => true];
    }

    public static function logout(): void {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], true);
        }
        session_destroy();
    }

    public static function changePassword(string $email, string $current, string $new1, string $new2): array {
        if ($new1 !== $new2) {
            return ['error' => 'Neue Passwörter stimmen nicht überein.'];
        }
        if (strlen($new1) < 8) {
            return ['error' => 'Passwort mindestens 8 Zeichen.'];
        }
        $users = json_read(self::USERS_FILE);
        $email = trim(strtolower($email));
        foreach (['admin', 'superadmin'] as $r) {
            if (isset($users[$r]) && strtolower($users[$r]['email'] ?? '') === $email) {
                if (!password_verify($current, $users[$r]['hash'] ?? '')) {
                    return ['error' => 'Aktuelles Passwort ist falsch.'];
                }
                $users[$r]['hash'] = password_hash($new1, PASSWORD_BCRYPT, ['cost' => 10]);
                if (json_write(self::USERS_FILE, $users)) {
                    return ['ok' => true];
                }
                return ['error' => 'Speichern fehlgeschlagen.'];
            }
        }
        return ['error' => 'Benutzer nicht gefunden.'];
    }

    private static function isRateLimited(): bool {
        $path = self::RATE_FILE;
        if (!is_file($path)) return false;
        $data = @json_decode(file_get_contents($path), true);
        if (!is_array($data) || ($data['time'] ?? 0) + self::RATE_WINDOW < time()) {
            @unlink($path);
            return false;
        }
        return ($data['count'] ?? 0) >= self::RATE_MAX_ATTEMPTS;
    }

    private static function recordFailedAttempt(): void {
        $path = self::RATE_FILE;
        $data = ['time' => time(), 'count' => 1];
        if (is_file($path)) {
            $old = @json_decode(file_get_contents($path), true);
            if (is_array($old) && ($old['time'] ?? 0) + self::RATE_WINDOW >= time()) {
                $data['time'] = $old['time'];
                $data['count'] = ($old['count'] ?? 0) + 1;
            }
        }
        file_put_contents($path, json_encode($data), LOCK_EX);
    }

    private static function clearRateLimit(): void {
        $path = self::RATE_FILE;
        if (is_file($path)) @unlink($path);
    }

    public static function requireAdmin(): void {
        if (!self::isLoggedIn()) {
            header('Location: ' . base_url('admin/login.php'));
            exit;
        }
    }

    public static function requireSuperadmin(): void {
        if (!self::isSuperadmin()) {
            header('Location: ' . base_url('admin/'));
            exit;
        }
    }
}
