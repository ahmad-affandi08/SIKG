<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * SYS-07: keadaan denyut scheduler dan antrean. Tanpa PHI. Umur null berarti belum pernah berdenyut.
 */
final readonly class HealthSnapshot
{
    public const OK = 'ok';

    public const DEGRADED = 'degraded';

    public function __construct(
        public string $status,
        public ?int $schedulerAgeSeconds,
        public ?int $queueAgeSeconds,
        public int $pendingJobs,
        public int $failedJobs,
        public int $staleAfterSeconds,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'scheduler_age_seconds' => $this->schedulerAgeSeconds,
            'queue_age_seconds' => $this->queueAgeSeconds,
            'pending_jobs' => $this->pendingJobs,
            'failed_jobs' => $this->failedJobs,
            'stale_after_seconds' => $this->staleAfterSeconds,
        ];
    }
}
