<?php

declare(strict_types=1);

namespace App\Infrastructure\Licenses;

use RuntimeException;

/**
 * JSON store for Licenses. Atomic writes with file locking.
 *
 * License row schema:
 * - license_id: string (UUID or unique id)
 * - order_id: string (links to OrdersJsonRepository)
 * - product_id: string
 * - license_type: string
 * - token_id: string (links to TokenStoreJson)
 * - created_at: string (ISO 8601)
 * - metadata: array (optional)
 */
final class LicensesJsonRepository
{
    private const STORE_VERSION = 1;

    public function __construct(
        private string $path
    ) {
    }

    /**
     * @param array<string, mixed> $license License record
     */
    public function create(array $license): void
    {
        $licenseId = $license['license_id'] ?? null;
        if (!is_string($licenseId) || $licenseId === '') {
            throw new RuntimeException('License must have license_id', 1);
        }

        $this->withLock(function (array $data) use ($license, $licenseId): array {
            if (isset($data['licenses'][$licenseId])) {
                throw new RuntimeException('License already exists: ' . $licenseId, 2);
            }
            $normalized = $this->normalizeLicense($license);
            $data['licenses'][$licenseId] = $normalized;
            return $data;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $licenseId): ?array
    {
        $data = $this->read();
        return $data['licenses'][$licenseId] ?? null;
    }

    /**
     * @param array<string, mixed> $license
     * @return array<string, mixed>
     */
    private function normalizeLicense(array $license): array
    {
        $default = [
            'metadata' => [],
        ];
        $out = array_merge($default, $license);
        if (empty($out['created_at'])) {
            $out['created_at'] = date('c');
        }
        return $out;
    }

    private function read(): array
    {
        if (!is_file($this->path)) {
            return $this->initialStructure();
        }
        $raw = file_get_contents($this->path);
        if ($raw === false) {
            return $this->initialStructure();
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : $this->initialStructure();
    }

    private function initialStructure(): array
    {
        return [
            'meta' => [
                'version' => self::STORE_VERSION,
                'updated_at' => date('c'),
            ],
            'licenses' => [],
        ];
    }

    private function withLock(callable $modify): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($this->path, 'c+');
        if ($fp === false) {
            throw new RuntimeException('Cannot open licenses store: ' . $this->path, 10);
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new RuntimeException('Cannot acquire lock on licenses store', 11);
        }

        try {
            $raw = stream_get_contents($fp);
            $data = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
            $data = is_array($data) ? $data : $this->initialStructure();

            $modified = $modify($data);
            if (!is_array($modified)) {
                throw new RuntimeException('Modify callback must return array', 12);
            }

            $modified['meta'] = [
                'version' => self::STORE_VERSION,
                'updated_at' => date('c'),
            ];
            $json = json_encode($modified, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new RuntimeException('JSON encode failed', 13);
            }

            $tmp = $dir . '/.licenses.' . getmypid() . '.' . bin2hex(random_bytes(8)) . '.tmp';
            if (file_put_contents($tmp, $json, LOCK_EX) === false) {
                throw new RuntimeException('Cannot write temp file', 14);
            }
            if (!rename($tmp, $this->path)) {
                @unlink($tmp);
                throw new RuntimeException('Cannot atomically replace store file', 15);
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
