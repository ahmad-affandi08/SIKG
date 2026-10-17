<?php

declare(strict_types=1);

namespace Src\Clinical\Contracts;

use DateTimeImmutable;

/**
 * Kunjungan terakhir seorang pasien yang sudah ditutup: dasar pengingat kontrol berkala (P2-03).
 */
final readonly class RecallCandidate
{
    public function __construct(public string $encounterId, public string $patientId, public string $doctorId, public DateTimeImmutable $closedAt) {}
}
