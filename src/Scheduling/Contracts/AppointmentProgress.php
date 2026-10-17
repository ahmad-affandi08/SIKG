<?php

declare(strict_types=1);

namespace Src\Scheduling\Contracts;

/**
 * Kontrak bagi Clinical untuk menandai janji sedang diperiksa dan selesai ketika kunjungan dimulai dan ditutup.
 */
interface AppointmentProgress
{
    /**
     * @param  string|null  $chairId  kursi yang dipakai bila belum ditetapkan
     */
    public function markInProgress(string $actorId, string $appointmentId, ?string $chairId = null): void;

    public function markCompleted(string $actorId, string $appointmentId): void;
}
