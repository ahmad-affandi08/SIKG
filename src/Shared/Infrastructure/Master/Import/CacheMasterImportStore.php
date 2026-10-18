<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Master\Import;

use Illuminate\Support\Facades\Cache;
use Src\Shared\Application\Master\Import\MasterImportStore;
use Src\Shared\Application\TenantContext;

/**
 * Baris sah impor master disimpan sementara di cache (driver `database` pada shared hosting) selama satu jam, terikat ke
 * klinik, pengguna, dan jenis master. Berisi data master saja, tidak ada data pasien.
 */
final class CacheMasterImportStore implements MasterImportStore
{
    private const TTL_SECONDS = 3600;

    public function __construct(private readonly TenantContext $tenant) {}

    public function put(string $token, string $actorId, string $entityKey, array $rows, array $summary): void
    {
        Cache::put($this->key($token), [
            'clinic' => $this->tenant->clinicId(), 'actor' => $actorId, 'entity' => $entityKey, 'rows' => $rows, 'summary' => $summary,
        ], self::TTL_SECONDS);
    }

    public function get(string $token, string $actorId, string $entityKey): ?array
    {
        $payload = $this->payload($token, $actorId, $entityKey);
        if ($payload === null || ! is_array($payload['rows'] ?? null)) {
            return null;
        }

        /** @var list<array{row: int, data: array<string, mixed>}> $rows */
        $rows = $payload['rows'];

        return $rows;
    }

    public function summary(string $token, string $actorId, string $entityKey): ?array
    {
        $summary = $this->payload($token, $actorId, $entityKey)['summary'] ?? null;

        /** @var array<string, mixed>|null $summary */
        return is_array($summary) ? $summary : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payload(string $token, string $actorId, string $entityKey): ?array
    {
        $payload = Cache::get($this->key($token));
        if (! is_array($payload)
            || ($payload['clinic'] ?? null) !== $this->tenant->clinicId()
            || ($payload['actor'] ?? null) !== $actorId
            || ($payload['entity'] ?? null) !== $entityKey) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    public function claim(string $token): bool
    {
        return Cache::add($this->key($token).':claim', true, self::TTL_SECONDS);
    }

    public function release(string $token): void
    {
        Cache::forget($this->key($token).':claim');
    }

    public function forget(string $token): void
    {
        Cache::forget($this->key($token));
        Cache::forget($this->key($token).':claim');
    }

    private function key(string $token): string
    {
        return 'master-import:'.$token;
    }
}
