<?php

declare(strict_types=1);

namespace App\Infrastructure\Fulfillment;

use RuntimeException;

/**
 * JSON store for Fulfillment status and structured data per purchase_id.
 * Atomic writes with file locking. Directories created lazily.
 *
 * Fulfillment row schema:
 * - purchase_id: string
 * - status: string (pending, processing, fulfilled, failed)
 * - delivered_at: ?string (ISO 8601, when status became fulfilled)
 * - attempt_count: int
 * - last_error_code: ?string
 * - last_error_message: ?string (short)
 * - token_id: ?string (links to TokenStoreJson)
 * - license_key_hash: ?string (sha256 hex of license key, never store plaintext)
 * - updated_at: string (ISO 8601)
 */
final class FulfillmentRepository
{
    private const STORE_VERSION = 2;

    public function __construct(
        private string $path
    ) {
    }

    /**
     * Set full fulfillment record. Merges with existing record, overwriting provided keys.
     *
     * @param array<string, mixed> $record Must include purchase_id and status
     */
    public function set(string $purchaseId, array $record): void
    {
        $this->withLock(function (array $data) use ($purchaseId, $record): array {
            $existing = $data['fulfillments'][$purchaseId] ?? [];
            $merged = array_merge($this->defaultRow($purchaseId), is_array($existing) ? $existing : [], $record);
            $merged['purchase_id'] = $purchaseId;
            $merged['updated_at'] = date('c');
            $data['fulfillments'][$purchaseId] = $merged;
            return $data;
        });
    }

    /**
     * Set status and optionally error fields. Increments attempt_count.
     *
     * @param array<string, mixed> $extra Optional: last_error_code, last_error_message, delivered_at, token_id, license_key_hash
     */
    public function setStatus(string $purchaseId, string $status, array $extra = []): void
    {
        $this->withLock(function (array $data) use ($purchaseId, $status, $extra): array {
            $existing = $data['fulfillments'][$purchaseId] ?? $this->defaultRow($purchaseId);
            $existing['status'] = $status;
            $existing['updated_at'] = date('c');
            if ($status === 'processing') {
                $existing['attempt_count'] = ((int) ($existing['attempt_count'] ?? 0)) + 1;
            }

            if (isset($extra['last_error_code'])) {
                $existing['last_error_code'] = $extra['last_error_code'];
            }
            if (isset($extra['last_error_message'])) {
                $existing['last_error_message'] = $extra['last_error_message'];
            }
            if (isset($extra['delivered_at'])) {
                $existing['delivered_at'] = $extra['delivered_at'];
            }
            if (isset($extra['token_id'])) {
                $existing['token_id'] = $extra['token_id'];
            }
            if (isset($extra['license_key_hash'])) {
                $existing['license_key_hash'] = $extra['license_key_hash'];
            }

            $data['fulfillments'][$purchaseId] = $existing;
            return $data;
        });
    }

    /**
     * @return array{status: string, delivered_at: ?string, attempt_count: int, last_error_code: ?string, last_error_message: ?string, token_id: ?string, license_key_hash: ?string, updated_at: string}|null
     */
    public function get(string $purchaseId): ?array
    {
        $data = $this->read();
        $row = $data['fulfillments'][$purchaseId] ?? null;
        if ($row === null) {
            return null;
        }
        return [
            'status' => $row['status'] ?? 'pending',
            'delivered_at' => $row['delivered_at'] ?? null,
            'attempt_count' => (int) ($row['attempt_count'] ?? 0),
            'last_error_code' => $row['last_error_code'] ?? null,
            'last_error_message' => $row['last_error_message'] ?? null,
            'token_id' => $row['token_id'] ?? null,
            'license_key_hash' => $row['license_key_hash'] ?? null,
            'updated_at' => $row['updated_at'] ?? '',
        ];
    }

    public function has(string $purchaseId): bool
    {
        $data = $this->read();
        return isset($data['fulfillments'][$purchaseId]);
    }

    /**
     * For backward compat: set simple status + metadata (used by old callers).
     *
     * @param array<string, mixed> $metadata
     */
    public function setStatusSimple(string $purchaseId, string $status, array $metadata = []): void
    {
        $extra = [];
        if (isset($metadata['last_error_code'])) {
            $extra['last_error_code'] = $metadata['last_error_code'];
        }
        if (isset($metadata['last_error_message'])) {
            $extra['last_error_message'] = $metadata['last_error_message'];
        }
        if (isset($metadata['delivered_at'])) {
            $extra['delivered_at'] = $metadata['delivered_at'];
        }
        if (isset($metadata['token_id'])) {
            $extra['token_id'] = $metadata['token_id'];
        }
        if (isset($metadata['license_key_hash'])) {
            $extra['license_key_hash'] = $metadata['license_key_hash'];
        }
        $this->setStatus($purchaseId, $status, $extra);
    }

    private function defaultRow(string $purchaseId): array
    {
        return [
            'purchase_id' => $purchaseId,
            'status' => 'pending',
            'delivered_at' => null,
            'attempt_count' => 0,
            'last_error_code' => null,
            'last_error_message' => null,
            'token_id' => null,
            'license_key_hash' => null,
            'updated_at' => date('c'),
        ];
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
        return is_array($data) ? $this->migrateStructure($data) : $this->initialStructure();
    }

    private function migrateStructure(array $data): array
    {
        $fulfillments = $data['fulfillments'] ?? [];
        foreach ($fulfillments as $pid => $row) {
            if (!is_array($row)) {
                continue;
            }
            $default = $this->defaultRow($pid);
            $fulfillments[$pid] = array_merge($default, $row);
            $fulfillments[$pid]['purchase_id'] = $pid;
        }
        $data['fulfillments'] = $fulfillments;
        return $data;
    }

    private function initialStructure(): array
    {
        return [
            'meta' => [
                'version' => self::STORE_VERSION,
                'updated_at' => date('c'),
            ],
            'fulfillments' => [],
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
            throw new RuntimeException('Cannot open fulfillment store: ' . $this->path, 1);
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new RuntimeException('Cannot acquire lock on fulfillment store', 2);
        }

        try {
            $raw = stream_get_contents($fp);
            $data = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
            $data = is_array($data) ? $this->migrateStructure($data) : $this->initialStructure();

            $modified = $modify($data);
            if (!is_array($modified)) {
                throw new RuntimeException('Modify callback must return array', 3);
            }

            $modified['meta'] = [
                'version' => self::STORE_VERSION,
                'updated_at' => date('c'),
            ];
            $json = json_encode($modified, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new RuntimeException('JSON encode failed', 4);
            }

            $tmp = $dir . '/.fulfillment.' . getmypid() . '.' . bin2hex(random_bytes(8)) . '.tmp';
            if (file_put_contents($tmp, $json, LOCK_EX) === false) {
                throw new RuntimeException('Cannot write temp file', 5);
            }
            if (!rename($tmp, $this->path)) {
                @unlink($tmp);
                throw new RuntimeException('Cannot atomically replace store file', 6);
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
