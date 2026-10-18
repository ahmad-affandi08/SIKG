<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Online;

use Illuminate\Support\Facades\Cache;
use Src\Billing\Application\Online\WebhookReplayGuard;

/**
 * Request-Id disimpan di cache (driver `database` pada shared hosting) selama satu jam, lebih lama dari batas umur timestamp
 * notifikasi, sehingga notifikasi yang sama tidak dapat diputar ulang selama masih dianggap segar.
 */
final class CacheWebhookReplayGuard implements WebhookReplayGuard
{
    private const TTL_SECONDS = 3600;

    public function firstSeen(string $clinicId, string $requestId): bool
    {
        return Cache::add($this->key($clinicId, $requestId), true, self::TTL_SECONDS);
    }

    public function forget(string $clinicId, string $requestId): void
    {
        Cache::forget($this->key($clinicId, $requestId));
    }

    private function key(string $clinicId, string $requestId): string
    {
        return 'doku-webhook:'.$clinicId.':'.hash('sha256', $requestId);
    }
}
