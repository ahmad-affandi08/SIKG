<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Nomor berurut (nomor RM, nomor invoice, nomor antrean) dari tabel `sequences` dengan kunci baris.
 * Dilarang MAX()+1 (ADR-0004). Harus dipanggil di dalam transaksi pemakainya agar nomor tidak bolong bila gagal.
 */
interface SequenceGenerator
{
    /**
     * @param  non-empty-string  $name  contoh `medical_record_no`, `invoice_no`, `queue_no`
     * @param  string  $scope  pembatas, contoh `clinic:{id}:2026` atau tanggal antrean; kosong bila global
     */
    public function next(string $name, string $scope = ''): int;
}
