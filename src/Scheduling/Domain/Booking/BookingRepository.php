<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain\Booking;

use DateTimeImmutable;

interface BookingRepository
{
    public function settings(): BookingSettings;

    public function saveSettings(string $actorId, BookingSettings $settings): void;

    /** Slug sudah dipakai klinik lain? (global, di luar cakupan klinik) */
    public function slugTaken(string $slug, bool $exceptOwnClinic): bool;

    /**
     * Klinik pemilik slug beserta pengaturannya; null bila slug tidak dikenal atau booking dimatikan. Di luar cakupan klinik.
     *
     * @return array{clinic_id: string, settings: BookingSettings}|null
     */
    public function resolveSlug(string $slug): ?array;

    public function add(string $id, string $tokenHash, string $doctorId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, string $patientName, string $phone, string $phoneHash, ?string $complaint, BookingStatus $status, DateTimeImmutable $expiresAt, ?string $codeHash, ?DateTimeImmutable $codeExpiresAt, ?string $ipHash): void;

    public function findByTokenHash(string $tokenHash): ?BookingRecord;

    public function find(string $id): ?BookingRecord;

    public function lockForUpdate(string $id): ?BookingRecord;

    /** Pemakaian aktif nomor: permintaan yang menahan slot dan janji hasil booking yang belum lewat. */
    public function activeCountForPhone(string $phoneHash, DateTimeImmutable $now): int;

    /** Permintaan baru yang masuk ke klinik sejak waktu itu (semua nomor). */
    public function requestsSince(DateTimeImmutable $since): int;

    /** Jumlah kode yang dikirim ke nomor dalam rentang waktu terakhir. */
    public function codesSentSince(string $phoneHash, DateTimeImmutable $since): int;

    /** Permintaan yang menahan slot dokter dan beririsan dengan rentang itu. */
    public function holdsBetween(string $doctorId, DateTimeImmutable $from, DateTimeImmutable $to, DateTimeImmutable $now): int;

    /** Mengganti kode; masa tahan slot ikut menjadi masa berlaku kode. */
    public function setCode(string $id, string $codeHash, DateTimeImmutable $expiresAt): void;

    public function bumpAttempts(string $id): void;

    /** Nomor terbukti milik pendaftar: slot ditahan sampai `holdUntil` (masa tahan klinik). */
    public function markVerified(string $id, DateTimeImmutable $at, DateTimeImmutable $holdUntil): void;

    public function transition(string $id, BookingStatus $status, ?string $actorId, ?string $note, ?string $patientId, ?string $appointmentId, DateTimeImmutable $at): void;

    /**
     * @return list<array{id: string, status: string, status_label: string, patient_name: string, doctor_id: string, starts_at: DateTimeImmutable, ends_at: DateTimeImmutable, complaint: string|null, verified: bool, patient_id: string|null, appointment_id: string|null, decision_note: string|null, created_at: DateTimeImmutable, phone_masked: string}> permintaan yang menunggu atau sudah diputuskan; nomor HP hanya tersamar
     */
    public function listForDesk(int $limit): array;

    public function phoneOf(string $id): ?string;

    public function phoneHashOf(string $id): ?string;

    /** @return int jumlah permintaan yang kedaluwarsa */
    public function expireDue(DateTimeImmutable $now): int;
}
