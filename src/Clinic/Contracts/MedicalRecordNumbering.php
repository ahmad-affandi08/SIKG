<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Penomoran rekam medis sesuai format yang diatur klinik (SYS-04). Harus dipanggil di dalam transaksi pemakainya
 * agar nomor tidak bolong bila pendaftaran gagal.
 */
interface MedicalRecordNumbering
{
    public function next(): string;
}
