<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain;

use DateTimeImmutable;

interface AppointmentRepository
{
    public function add(
        string $actorId,
        string $patientId,
        string $doctorId,
        ?string $chairId,
        TimeSlot $slot,
        AppointmentStatus $status,
        string $source,
        ?string $complaint,
        ?int $queueNo = null,
        ?string $queueDate = null,
        ?DateTimeImmutable $checkedInAt = null,
    ): string;

    public function find(string $id): ?AppointmentRecord;

    /** Mengunci baris janji selama transaksi pemanggil. */
    public function findForUpdate(string $id): ?AppointmentRecord;

    /**
     * Mengubah dokter, kursi, dan waktu (jadwal ulang). Status kembali Dijadwalkan.
     */
    public function reschedule(string $actorId, string $id, string $doctorId, ?string $chairId, TimeSlot $slot, string $reason): void;

    public function changeStatus(string $actorId, string $id, AppointmentStatus $status, ?string $reason = null): void;

    public function checkIn(string $actorId, string $id, int $queueNo, string $queueDate, DateTimeImmutable $at): void;

    public function assignChair(string $actorId, string $id, string $chairId): void;

    /**
     * Resource yang bentrok dengan rentang itu (janji yang masih menempati slot), atau null bila bebas.
     *
     * @return 'doctor'|'chair'|'patient'|null
     */
    public function findConflict(string $doctorId, ?string $chairId, string $patientId, TimeSlot $slot, ?string $ignoreId = null): ?string;

    /**
     * Janji yang mulai pada rentang waktu UTC, opsional per dokter atau kursi, urut waktu mulai.
     *
     * @return list<AppointmentRecord>
     */
    public function between(DateTimeImmutable $from, DateTimeImmutable $to, ?string $doctorId = null, ?string $chairId = null): array;

    /**
     * Janji dengan nomor antrean pada tanggal lokal itu, urut nomor antrean.
     *
     * @return list<AppointmentRecord>
     */
    public function queue(string $queueDate): array;
}
