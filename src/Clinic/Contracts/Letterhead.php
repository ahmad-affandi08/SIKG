<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Kop surat klinik untuk cetakan (resep, kwitansi): nama, alamat, nomor izin, teks header, dan logo sebagai data URI.
 */
final readonly class Letterhead
{
    public function __construct(
        public string $clinicName,
        public ?string $address,
        public ?string $licenseNo,
        public ?string $headerText,
        public ?string $logoDataUri,
    ) {}
}
