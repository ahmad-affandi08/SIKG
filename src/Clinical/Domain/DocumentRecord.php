<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

final readonly class DocumentRecord
{
    public function __construct(
        public string $id,
        public string $patientId,
        public ?string $encounterId,
        public DocumentType $type,
        public string $number,
        public string $title,
        public string $body,
        public string $doctorId,
        public string $doctorName,
        public ?string $doctorSip,
        public string $issuedBy,
        public DateTimeImmutable $issuedAt,
    ) {}
}
