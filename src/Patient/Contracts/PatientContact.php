<?php

declare(strict_types=1);

namespace Src\Patient\Contracts;

/**
 * Tujuan pengingat untuk satu pasien yang bersedia (JDW-07). Hanya diberikan kepada konteks Scheduling untuk pengiriman;
 * isinya PHI, jangan ditulis ke log.
 */
final readonly class PatientContact
{
    public function __construct(public string $patientId, public ?string $email, public ?string $telegramChatId) {}
}
