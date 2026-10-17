<?php

declare(strict_types=1);

namespace Src\Billing\Application;

/**
 * Baca daftar tagihan (halaman daftar). Data per baris memakai kolom turunan yang disimpan bersama tagihan.
 */
interface InvoiceListing
{
    /**
     * @param  array{status?: string|null, number?: string|null, patient_id?: string|null, from?: string|null, to?: string|null}  $filters  tanggal lokal klinik YYYY-MM-DD
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function list(array $filters, int $page, int $perPage): array;
}
