<?php

declare(strict_types=1);

namespace Src\Shared\Application\Setup;

/**
 * Konteks menyumbang data awal untuk satu klinik (seeder yang sama dengan `db:seed`). Aman dijalankan berulang.
 */
interface StarterDataContributor
{
    /** Kunci hasil, mis. `procedures`. */
    public function starterKey(): string;

    /** @return int jumlah baris yang ditambahkan */
    public function seedClinic(string $clinicId): int;
}
