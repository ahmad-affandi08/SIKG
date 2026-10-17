<?php

declare(strict_types=1);

namespace Src\Billing\Application;

/**
 * Nomor invoice dan kwitansi berurut dari tabel `sequences` dalam transaksi pemakainya (ADR-0004). Nomor invoice hanya
 * dikeluarkan saat tagihan diterbitkan sehingga draft tidak menghabiskan nomor.
 */
interface BillingNumbering
{
    public function invoiceNumber(): string;

    public function receiptNumber(): string;
}
