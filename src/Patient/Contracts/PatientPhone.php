<?php

declare(strict_types=1);

namespace Src\Patient\Contracts;

/**
 * Nama dan nomor HP pasien untuk pengingat (P2-03). `phone` berbentuk internasional tanpa plus (628123456789) dan sudah
 * didekripsi: hanya dipakai membuat tautan WhatsApp atau kirim API, tidak ditulis ke log.
 */
final readonly class PatientPhone
{
    public function __construct(public string $patientId, public string $name, public ?string $phone) {}
}
