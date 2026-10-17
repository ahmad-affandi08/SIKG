<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Kop surat klinik yang sama untuk SEMUA cetakan (ADR-0008): nama, alamat, nomor izin, satu teks tambahan kop, dan logo sebagai
 * data URI. `footnote` adalah catatan kaki opsional yang dicetak hanya pada resep dan kwitansi.
 */
final readonly class Letterhead
{
    public function __construct(
        public string $clinicName,
        public ?string $address,
        public ?string $licenseNo,
        public ?string $headerText,
        public ?string $logoDataUri,
        public ?string $footnote = null,
    ) {}

    /**
     * Bentuk yang dipakai templat PDF; satu pemetaan untuk semua cetakan agar kop tidak pernah berbeda antardokumen.
     *
     * @return array{name: string, address: string|null, license: string|null, header: string|null, logo: string|null, footnote: string|null}
     */
    public function toArray(): array
    {
        return ['name' => $this->clinicName, 'address' => $this->address, 'license' => $this->licenseNo, 'header' => $this->headerText, 'logo' => $this->logoDataUri, 'footnote' => $this->footnote];
    }
}
