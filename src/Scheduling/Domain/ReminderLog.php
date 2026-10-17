<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain;

use DateTimeImmutable;

/**
 * Catatan pengingat per janji, jenis, dan saluran (JDW-07). Dasar idempotensi pengiriman.
 */
interface ReminderLog
{
    /**
     * @return array{status: string, attempts: int}|null
     */
    public function state(string $appointmentId, string $kind, string $channel): ?array;

    public function record(string $appointmentId, string $kind, string $channel, bool $sent, DateTimeImmutable $at): void;
}
