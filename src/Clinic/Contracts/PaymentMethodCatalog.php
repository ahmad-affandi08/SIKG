<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Kontrak baca metode pembayaran (MST-06) untuk Billing. Metode nonaktif tetap dapat dibaca agar pembayaran lama tampil utuh.
 */
interface PaymentMethodCatalog
{
    public function find(string $id): ?PaymentMethodSnapshot;

    /**
     * @param  list<string>  $ids
     * @return array<string, PaymentMethodSnapshot> id => metode
     */
    public function byIds(array $ids): array;

    /**
     * @return list<PaymentMethodSnapshot> hanya yang aktif, urut nama
     */
    public function active(): array;

    /**
     * Id semua metode bertipe tunai, termasuk yang sudah nonaktif; dasar hitung uang fisik di laci kas (KSR-08).
     *
     * @return list<string>
     */
    public function cashMethodIds(): array;
}
