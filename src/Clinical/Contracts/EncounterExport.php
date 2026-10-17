<?php

declare(strict_types=1);

namespace Src\Clinical\Contracts;

use DateTimeImmutable;

/**
 * Kunjungan yang sudah ditutup, dalam bentuk yang dibutuhkan pelaporan ke platform interoperabilitas (P2-01):
 * diagnosa ICD-10 dan tindakan per kunjungan. Tanpa catatan klinis bebas.
 */
final readonly class EncounterExport
{
    /**
     * @param  list<array{code: string, name: string}>  $diagnoses
     * @param  list<array{procedure_id: string, code: string, name: string}>  $procedures
     */
    public function __construct(
        public string $id,
        public string $patientId,
        public string $doctorId,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $closedAt,
        public bool $closed,
        public array $diagnoses,
        public array $procedures,
    ) {}
}
