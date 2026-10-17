<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

/**
 * Bukti penandatanganan dokumen: siapa yang menandatangani, kapan, oleh petugas mana, dan sidik jari (SHA-256) isi dokumen dan
 * PDF bertanda tangan yang tersimpan di rekam medis.
 */
final readonly class DocumentSignatureRecord
{
    public function __construct(
        public string $id,
        public string $documentId,
        public string $patientId,
        public string $signerName,
        public string $signerRole,
        public ?string $witnessName,
        public string $pdfPath,
        public bool $pdfEncrypted,
        public string $pdfSha256,
        public string $bodySha256,
        public string $signedBy,
        public DateTimeImmutable $signedAt,
    ) {}
}
