<?php

declare(strict_types=1);

namespace App\Infrastructure\Webhooks;

use RuntimeException;

/**
 * JSON store for processed webhook events (idempotency).
 * Atomic writes with file locking. Keys by event ID.
 *
 * Schema: { "meta": { "version": 1, "updated_at": "..." }, "events": { "evt_xxx": { "id": "evt_xxx", "type": "...", "processed_at": "..." } } }
 */
final class WebhookEventStore
{
    private const STORE_VERSION = 1;

    public function __construct(
        private string $path
    ) {
    }

    public function has(string $eventId): bool
    {
        $data = $this->read();
        return isset($data['events'][$eventId]);
    }

    public function markProcessed(string $eventId, string $eventType): void
    {
        $this->withLock(function (array $data) use ($eventId, $eventType): array {
            $data['events'][$eventId] = [
                'id' => $eventId,
                'type' => $eventType,
                'processed_at' => date('c'),
            ];
            return $data;
        });
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
            'events' => [],
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
            throw new RuntimeException('Cannot open webhook event store: ' . $this->path, 1);
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new RuntimeException('Cannot acquire lock on webhook event store', 2);
        }

        try {
            $raw = stream_get_contents($fp);
            $data = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
            $data = is_array($data) ? $data : $this->initialStructure();

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

            $tmp = $dir . '/.processed-events.' . getmypid() . '.' . bin2hex(random_bytes(8)) . '.tmp';
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
