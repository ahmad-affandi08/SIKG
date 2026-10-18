<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain\Booking;

use DateTimeImmutable;

/**
 * Satu permintaan booking online. Nomor HP tidak ada di sini: ia hanya disimpan terenkripsi di basis data dan dibuka petugas
 * lewat repositori. `codeHash` adalah HMAC kode verifikasi, bukan kodenya.
 */
final readonly class BookingRecord
{
    public function __construct(
        public string $id,
        public string $doctorId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $patientName,
        public ?string $complaint,
        public BookingStatus $status,
        public bool $verified,
        public ?string $patientId,
        public ?string $appointmentId,
        public DateTimeImmutable $expiresAt,
        public ?string $decisionNote,
        public ?string $codeHash,
        public ?DateTimeImmutable $codeExpiresAt,
        public int $codeAttempts,
        public int $codeSentCount,
        public DateTimeImmutable $createdAt,
    ) {}
}
