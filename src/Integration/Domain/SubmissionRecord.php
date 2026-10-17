<?php

declare(strict_types=1);

namespace Src\Integration\Domain;

use DateTimeImmutable;

final readonly class SubmissionRecord
{
    public function __construct(
        public string $id,
        public string $encounterId,
        public string $patientId,
        public SubmissionStatus $status,
        public int $attempts,
        public ?DateTimeImmutable $nextAttemptAt,
        public ?string $lastError,
        public ?string $encounterIhsId,
        public ?DateTimeImmutable $sentAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
