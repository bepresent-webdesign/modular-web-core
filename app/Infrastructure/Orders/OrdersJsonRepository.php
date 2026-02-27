<?php

declare(strict_types=1);

namespace App\Infrastructure\Orders;

use RuntimeException;

/**
 * JSON store for Orders. Atomic writes with file locking.
 *
 * Order row schema:
 * - order_id: string (UUID or unique id)
 * - stripe_session_id: string
 * - stripe_event_id: string
 * - customer_email: ?string
 * - product_id: string
 * - license_type: string
 * - amount_minor: int
 * - currency: string
 * - created_at: string (ISO 8601)
 * - metadata: array (optional)
 */
final class OrdersJsonRepository
{
    private const STORE_VERSION = 1;

    public function __construct(
        private string $path
    ) {
    }

    /**
     * @param array<string, mixed> $order Order record
     */
    public function create(array $order): void
    {
        $orderId = $order['order_id'] ?? null;
        if (!is_string($orderId) || $orderId === '') {
            throw new RuntimeException('Order must have order_id', 1);
        }

        $this->withLock(function (array $data) use ($order, $orderId): array {
            if (isset($data['orders'][$orderId])) {
                throw new RuntimeException('Order already exists: ' . $orderId, 2);
            }
            $normalized = $this->normalizeOrder($order);
            $data['orders'][$orderId] = $normalized;
            return $data;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $orderId): ?array
    {
        $data = $this->read();
        return $data['orders'][$orderId] ?? null;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function normalizeOrder(array $order): array
    {
        $default = [
            'metadata' => [],
        ];
        $out = array_merge($default, $order);
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
            'orders' => [],
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
            throw new RuntimeException('Cannot open orders store: ' . $this->path, 10);
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new RuntimeException('Cannot acquire lock on orders store', 11);
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

            $tmp = $dir . '/.orders.' . getmypid() . '.' . bin2hex(random_bytes(8)) . '.tmp';
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
