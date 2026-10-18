<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Loyalty;

interface VoucherRepository
{
    /**
     * Voucher menurut kode (sudah dinormalkan), dikunci selama transaksi pemanggil agar batas pemakaian tidak terlampaui.
     */
    public function lockByCode(string $code): ?Voucher;

    public function find(string $id): ?Voucher;

    public function appliedTotal(string $voucherId): int;

    public function appliedByPatient(string $voucherId, string $patientId): int;
}
