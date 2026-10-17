<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

use DateTimeImmutable;

/**
 * Kontak pengingat pasien (JDW-07). Email dan ID obrolan Telegram sudah didekripsi di sini; jangan dicatat ke log.
 */
final readonly class ContactRecord
{
    public function __construct(
        public string $patientId,
        public ?string $email,
        public ?string $telegramChatId,
        public bool $remindersOptIn,
        public ?DateTimeImmutable $optInAt,
        public bool $linkPending,
    ) {}

    public function hasChannel(): bool
    {
        return $this->email !== null || $this->telegramChatId !== null;
    }
}
