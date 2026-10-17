<?php

declare(strict_types=1);

namespace Src\Scheduling\Contracts;

use DateTimeImmutable;

/**
 * Janji yang masih akan datang (dijadwalkan atau dikonfirmasi) untuk konteks lain, contoh perencanaan pengingat (P2-03).
 */
interface UpcomingAppointments
{
    /**
     * Janji dijadwalkan atau dikonfirmasi yang mulai pada rentang waktu UTC, urut waktu mulai.
     *
     * @return list<AppointmentInfo>
     */
    public function between(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Pasien (dari daftar itu) yang punya janji dijadwalkan atau dikonfirmasi setelah waktu tersebut.
     *
     * @param  list<string>  $patientIds
     * @return list<string>
     */
    public function patientsWithAppointmentAfter(array $patientIds, DateTimeImmutable $after): array;
}
