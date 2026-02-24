<?php

declare(strict_types=1);

/**
 * JSON-based token store with safe locking and atomic writes.
 * Supports server-side revocation and one-time / max-download enforcement.
 */
final class TokenStoreJson
{
    private const STORE_VERSION = 1;
    private const REQUIRED_FIELDS = ['token_id', 'status', 'created_at', 'exp', 'file_key', 'engine_version', 'max_downloads', 'download_count'];

    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? (
            defined('CORE_ROOT')
                ? CORE_ROOT . '/data/download_tokens.json'
                : dirname(__DIR__, 2) . '/data/download_tokens.json'
        );
    }

    /**
     * Get a token record by ID. No lock; read-only.
     */
    public function get(string $tokenId): ?array
    {
        $data = $this->readFile();
        return $data['tokens'][$tokenId] ?? null;
    }

    /**
     * Create or replace a token record. Validates required fields.
     */
    public function put(array $record): void
    {
        $this->validateRecord($record);
        $tokenId = $record['token_id'];
        $this->withLock(function (array $data) use ($record, $tokenId): array {
            $data['tokens'][$tokenId] = $this->normalizeRecord($record);
            return $data;
        });
    }

    /**
     * Revoke a token. Sets status=revoked and revoked_at.
     */
    public function revoke(string $tokenId, string $reason = ''): array
    {
        return $this->update($tokenId, function (array $record) use ($reason): array {
            $record['status'] = 'revoked';
            $record['revoked_at'] = date('c');
            if ($reason !== '') {
                $record['metadata'] = ($record['metadata'] ?? []) + ['reason' => $reason];
            }
            return $record;
        });
    }

    /**
     * Consume one download. Atomic. Validates active, not expired, under limit.
     *
     * @throws \RuntimeException not found (40), expired (41), revoked (42), exceeded (43)
     */
    public function consume(string $tokenId, array $ctx = []): array
    {
        $updated = null;
        $this->withLock(function (array $data) use ($tokenId, &$updated): array {
            $record = $data['tokens'][$tokenId] ?? null;
            if ($record === null) {
                throw new \RuntimeException('Token not found', 40);
            }
            if (($record['status'] ?? '') !== 'active') {
                throw new \RuntimeException('Token is not active (revoked/consumed/expired)', 42);
            }
            $exp = (int) ($record['exp'] ?? 0);
            if (time() > $exp) {
                throw new \RuntimeException('Token expired', 41);
            }
            $downloadCount = (int) ($record['download_count'] ?? 0);
            $maxDownloads = (int) ($record['max_downloads'] ?? 1);
            if ($downloadCount >= $maxDownloads) {
                throw new \RuntimeException('Max downloads exceeded', 43);
            }

            $record['download_count'] = $downloadCount + 1;
            $record['last_download_at'] = date('c');
            if ($record['download_count'] >= $maxDownloads) {
                $record['status'] = 'consumed';
                $record['consumed_at'] = date('c');
            }

            $data['tokens'][$tokenId] = $record;
            $updated = $record;
            return $data;
        });
        assert($updated !== null);
        return $updated;
    }

    /**
     * Update a token under exclusive lock. Pass record to fn, expect array back.
     */
    public function update(string $tokenId, callable $fn): array
    {
        $updated = null;
        $this->withLock(function (array $data) use ($tokenId, $fn, &$updated): array {
            $record = $data['tokens'][$tokenId] ?? null;
            if ($record === null) {
                throw new \RuntimeException('Token not found', 40);
            }
            $result = $fn($record);
            if (!is_array($result)) {
                throw new \RuntimeException('Update callback must return array', 50);
            }
            $data['tokens'][$tokenId] = $this->normalizeRecord($result);
            $updated = $data['tokens'][$tokenId];
            return $data;
        });
        assert($updated !== null);
        return $updated;
    }

    /**
     * Execute a read-modify-write under exclusive lock. Atomic temp-file + rename.
     */
    private function withLock(callable $modify): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($this->path, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Cannot open store file: ' . $this->path, 60);
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new \RuntimeException('Cannot acquire lock on store file', 61);
        }

        try {
            $raw = stream_get_contents($fp);
            $data = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
            $data = is_array($data) ? $data : $this->initialStructure();

            $modified = $modify($data);
            if (!is_array($modified)) {
                throw new \RuntimeException('Modify callback must return array', 62);
            }

            $this->touchMeta($modified);
            $json = json_encode($modified, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new \RuntimeException('JSON encode failed', 63);
            }

            $tmp = $dir . '/.download_tokens.' . getmypid() . '.' . bin2hex(random_bytes(8)) . '.tmp';
            if (file_put_contents($tmp, $json, LOCK_EX) === false) {
                throw new \RuntimeException('Cannot write temp file', 64);
            }
            if (!rename($tmp, $this->path)) {
                @unlink($tmp);
                throw new \RuntimeException('Cannot atomically replace store file', 65);
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function readFile(): array
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
            'tokens' => [],
        ];
    }

    private function touchMeta(array &$data): void
    {
        $data['meta'] = [
            'version' => self::STORE_VERSION,
            'updated_at' => date('c'),
        ];
    }

    private function validateRecord(array $record): void
    {
        foreach (self::REQUIRED_FIELDS as $key) {
            if (!array_key_exists($key, $record)) {
                throw new \RuntimeException("Missing required field: {$key}", 70);
            }
        }
    }

    private function normalizeRecord(array $record): array
    {
        $default = [
            'revoked_at' => null,
            'consumed_at' => null,
            'last_download_at' => null,
            'metadata' => [
                'order_ref' => null,
                'customer_ref' => null,
                'payment_provider' => null,
            ],
        ];
        $meta = $record['metadata'] ?? [];
        $default['metadata'] = array_merge($default['metadata'], is_array($meta) ? $meta : []);
        $out = array_merge($default, $record);
        if (empty($out['created_at'])) {
            $out['created_at'] = date('c');
        }
        return $out;
    }
}
