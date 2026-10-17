<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

/**
 * Keterangan yang dicetak pada setiap lembar ekspor: nama klinik, alamat, nomor izin, judul laporan, rentang, dan waktu cetak (zona klinik).
 */
final readonly class ReportContext
{
    public function __construct(
        public string $clinicName,
        public ?string $address,
        public ?string $license,
        public string $heading,
        public string $period,
        public string $printedAt,
    ) {}

    /** Baris kedua kop: alamat dan nomor izin. */
    public function kopLine(): string
    {
        return implode(' · ', array_filter([$this->address, $this->license === null || $this->license === '' ? null : 'Izin: '.$this->license], fn (?string $v) => $v !== null && $v !== ''));
    }
}
