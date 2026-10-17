<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

/**
 * Jenis metode pembayaran (MST-06). Pencatatan manual; tanpa integrasi payment gateway pada Fase 1.
 */
enum PaymentMethodType: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Transfer => 'Transfer bank',
            self::Qris => 'QRIS',
            self::Debit => 'Kartu debit',
            self::Credit => 'Kartu kredit',
        };
    }
}
