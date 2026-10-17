<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use DateTimeImmutable;

/**
 * Kejadian bisnis yang boleh didengarkan konteks lain. Implementasi harus `readonly` dan tanpa PHI.
 * Dikirim setelah commit transaksi (docs/rules/backend-ddd.md bagian 3).
 */
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;

    /**
     * Nama stabil, contoh `scheduling.patient_checked_in`.
     *
     * @return non-empty-string
     */
    public static function eventName(): string;
}
