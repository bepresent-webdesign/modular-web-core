<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchases;

use RuntimeException;

/**
 * JSON store for Purchases. Atomic writes with file locking.
 * Directories are created lazily (like WebhookEventStore).
 *
 * Purchase row schema:
 * - purchase_id, provider, provider_event_id, customer_email,
 *   product_id, license_type, amount, currency, created_at, metadata
 */
final class PurchaseRepository
{
    private const STORE_VERSION = 1;

    public function __construct(
        private string $path
    ) {
    }

    /**
     * @param array<string, mixed> $purchase Purchase record (from Purchase::create)
     */
    public function create(array $purchase): void
    {
        $purchaseId = $purchase['purchase_id'] ?? null;
        if (!is_string($purchaseId) || $purchaseId === '') {
            throw new RuntimeException('Purchase must have purchase_id', 1);
        }

        $this->withLock(function (array $data) use ($purchase, $purchaseId): array {
            if (isset($data['purchases'][$purchaseId])) {
                throw new RuntimeException('Purchase already exists: ' . $purchaseId, 2);
            }
            $data['purchases'][$purchaseId] = $purchase;
            return $data;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $purchaseId): ?array
    {
        $data = $this->read();
        return $data['purchases'][$purchaseId] ?? null;
    }

    /**
     * Find purchase by provider + provider_event_id (idempotency lookup).
     *
     * @return array<string, mixed>|null
     */
    public function getByProviderEvent(string $provider, string $providerEventId): ?array
    {
        $data = $this->read();
        foreach ($data['purchases'] ?? [] as $purchase) {
            if (($purchase['provider'] ?? '') === $provider
                && ($purchase['provider_event_id'] ?? '') === $providerEventId) {
                return $purchase;
            }
        }
        return null;
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
            'purchases' => [],
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
            throw new RuntimeException('Cannot open purchases store: ' . $this->path, 10);
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new RuntimeException('Cannot acquire lock on purchases store', 11);
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

            $tmp = $dir . '/.purchases.' . getmypid() . '.' . bin2hex(random_bytes(8)) . '.tmp';
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
