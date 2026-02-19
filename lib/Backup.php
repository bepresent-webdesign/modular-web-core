<?php
declare(strict_types=1);

/**
 * File-based backup/restore. Auto on content change, manual button, max 20.
 */
class Backup {
    private const MAX_BACKUPS = 20;
    private const BACKUP_PREFIX = 'backup_';

    public static function create(string $reason = 'manual'): array {
        ensure_dirs();
        $list = self::listBackups();
        while (count($list) >= self::MAX_BACKUPS) {
            $oldest = array_shift($list);
            if ($oldest && is_file(BACKUPS_DIR . '/' . $oldest)) {
                unlink(BACKUPS_DIR . '/' . $oldest);
            }
        }
        $id = self::BACKUP_PREFIX . date('Y-m-d_H-i-s') . '_' . substr(uniqid(), -4);
        $payload = [
            'created' => date('c'),
            'reason' => $reason,
            'content' => [],
            'uploads_manifest' => [],
        ];
        $files = array_diff(scandir(CONTENT_DIR) ?: [], ['.', '..']);
        foreach ($files as $f) {
            if (pathinfo($f, PATHINFO_EXTENSION) === 'json') {
                $payload['content'][$f] = file_get_contents(CONTENT_DIR . '/' . $f) ?: '{}';
            }
        }
        $imgDir = UPLOADS_DIR . '/images';
        if (is_dir($imgDir)) {
            foreach (scandir($imgDir) ?: [] as $f) {
                if ($f === '.' || $f === '..' || $f === '.trash') continue;
                $full = $imgDir . '/' . $f;
                if (is_file($full)) {
                    $payload['uploads_manifest'][$f] = base64_encode(file_get_contents($full));
                }
            }
        }
        $path = BACKUPS_DIR . '/' . $id . '.json';
        if (!file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX)) {
            return ['error' => 'Backup konnte nicht erstellt werden.'];
        }
        return ['ok' => true, 'id' => $id];
    }

    public static function listBackups(): array {
        if (!is_dir(BACKUPS_DIR)) return [];
        $out = [];
        foreach (scandir(BACKUPS_DIR) ?: [] as $f) {
            if (strpos($f, self::BACKUP_PREFIX) === 0 && substr($f, -5) === '.json') {
                $out[] = $f;
            }
        }
        rsort($out);
        return array_slice($out, 0, self::MAX_BACKUPS);
    }

    public static function restore(string $filename): array {
        $path = BACKUPS_DIR . '/' . $filename;
        if (!preg_match('/^backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}_[a-z0-9]+\.json$/', $filename) || !is_file($path)) {
            return ['error' => 'Backup nicht gefunden.'];
        }
        $payload = json_decode(file_get_contents($path), true);
        if (!is_array($payload) || !isset($payload['content'])) {
            return ['error' => 'Ungültiges Backup.'];
        }
        ensure_dirs();
        foreach ($payload['content'] as $name => $body) {
            if (preg_match('/^[a-z0-9_-]+\.json$/', $name)) {
                file_put_contents(CONTENT_DIR . '/' . $name, $body, LOCK_EX);
            }
        }
        $imgDir = UPLOADS_DIR . '/images';
        if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);
        foreach ($payload['uploads_manifest'] ?? [] as $fname => $b64) {
            if (preg_match('/^[a-zA-Z0-9._-]+$/', $fname)) {
                file_put_contents($imgDir . '/' . $fname, base64_decode($b64, true) ?: '');
            }
        }
        return ['ok' => true];
    }

    public static function getBackupInfo(string $filename): ?array {
        $path = BACKUPS_DIR . '/' . $filename;
        if (!is_file($path)) return null;
        $payload = json_decode(file_get_contents($path), true);
        if (!is_array($payload)) return null;
        return [
            'created' => $payload['created'] ?? '',
            'reason' => $payload['reason'] ?? 'unknown',
        ];
    }
}
