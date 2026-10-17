<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Health;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Clock;
use Src\Shared\Application\HealthMonitor;
use Src\Shared\Application\HealthSnapshot;

/**
 * Denyut disimpan di `system_heartbeats` oleh scheduler (cron) dan worker antrean. Cocok untuk shared hosting
 * karena tidak butuh proses yang selalu hidup (M21).
 */
final readonly class DatabaseHealthMonitor implements HealthMonitor
{
    public function __construct(private Clock $clock) {}

    public function beat(string $component): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        DB::table('system_heartbeats')->upsert(
            [['name' => $component, 'beat_at' => $now]],
            ['name'],
            ['beat_at'],
        );
    }

    public function snapshot(): HealthSnapshot
    {
        $stale = max(60, (int) config('sikg.heartbeat_stale_seconds'));
        $scheduler = $this->age('scheduler');
        $queue = $this->age('queue');
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();

        $healthy = $scheduler !== null && $scheduler <= $stale
            && $queue !== null && $queue <= $stale
            && $failed === 0;

        return new HealthSnapshot(
            $healthy ? HealthSnapshot::OK : HealthSnapshot::DEGRADED,
            $scheduler,
            $queue,
            $pending,
            $failed,
            $stale,
        );
    }

    private function age(string $component): ?int
    {
        $beat = DB::table('system_heartbeats')->where('name', $component)->value('beat_at');
        if (! is_string($beat)) {
            return null;
        }

        $at = new DateTimeImmutable($beat, new DateTimeZone('UTC'));

        return max(0, $this->clock->now()->getTimestamp() - $at->getTimestamp());
    }
}
