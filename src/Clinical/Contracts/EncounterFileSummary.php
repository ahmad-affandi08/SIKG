<?php

declare(strict_types=1);

namespace Src\Clinical\Contracts;

/**
 * Ringkasan berkas klinis (foto atau rontgen) pada kunjungan, untuk konteks lain yang menampilkannya, contoh foto progres ortodonti (P2-04).
 * Berkasnya sendiri dibuka lewat halaman berkas klinis yang memeriksa izin.
 */
final readonly class EncounterFileSummary
{
    public function __construct(public string $id, public string $kind, public string $kindLabel, public ?string $caption, public string $mime) {}
}
