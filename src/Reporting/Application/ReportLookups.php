<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

/**
 * Pilihan untuk penyaring laporan dan pemetaan pengguna ke dokter.
 */
interface ReportLookups
{
    /**
     * @return list<array{id: string, name: string}> semua dokter klinik termasuk yang nonaktif, urut nama
     */
    public function doctors(): array;

    /**
     * @return list<array{id: string, name: string}> semua metode bayar termasuk yang nonaktif, urut nama
     */
    public function paymentMethods(): array;

    /** Id dokter yang tertaut ke akun pengguna ini, bila ada. */
    public function doctorIdForUser(string $userId): ?string;

    /** Tanggal hari ini menurut kalender klinik (YYYY-MM-DD). */
    public function today(): string;
}
