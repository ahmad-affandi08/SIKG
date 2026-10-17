<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Penomoran invoice sesuai format yang diatur klinik (SYS-04). Harus dipanggil di dalam transaksi pemakainya
 * agar nomor tidak bolong bila penerbitan gagal.
 */
interface InvoiceNumbering
{
    public function next(): string;
}
