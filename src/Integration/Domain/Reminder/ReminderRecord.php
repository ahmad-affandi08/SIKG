<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

use DateTimeImmutable;

final readonly class ReminderRecord
{
    public function __construct(
        public string $id,
        public ReminderKind $kind,
        public ReminderStatus $status,
        public string $patientId,
        public ?string $appointmentId,
        public ?string $encounterId,
        public DateTimeImmutable $dueAt,
        public int $attempts,
        public ?string $lastError,
        public ?DateTimeImmutable $sentAt,
        public ?string $channel,
    ) {}
}
