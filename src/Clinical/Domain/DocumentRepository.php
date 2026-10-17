<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

/**
 * Penyimpanan dokumen klinis yang diterbitkan. Hanya-tambah: tidak ada ubah dan hapus (M5).
 */
interface DocumentRepository
{
    public function add(string $actorId, string $patientId, ?string $encounterId, DocumentType $type, ?string $templateId, string $number, string $title, string $body, string $doctorId, string $doctorName, ?string $doctorSip, DateTimeImmutable $at): string;

    public function find(string $id): ?DocumentRecord;

    /**
     * Dokumen pasien, terbaru lebih dulu.
     *
     * @return list<DocumentRecord>
     */
    public function forPatient(string $patientId): array;
}
