<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

final readonly class ClinicalFileRecord
{
    public function __construct(
        public string $id,
        public string $patientId,
        public string $encounterId,
        public ?int $toothFdi,
        public string $path,
        public string $mime,
        public int $size,
        public FileKind $kind,
        public bool $encrypted,
        public ?string $caption,
        public string $uploadedBy,
        public DateTimeImmutable $uploadedAt,
    ) {}
}
