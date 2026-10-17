<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

interface PurchaseOrderRepository
{
    public function add(PurchaseOrder $order, string $actorId): void;

    /**
     * Pesanan beserta barisnya, dikunci selama transaksi pemanggil.
     */
    public function lock(string $id): ?PurchaseOrder;

    public function find(string $id): ?PurchaseOrder;

    /**
     * Menyimpan status dan jumlah diterima per baris.
     */
    public function save(PurchaseOrder $order, string $actorId): void;

    /**
     * @return list<PurchaseOrder> terbaru lebih dulu
     */
    public function recent(?PurchaseOrderStatus $status, int $limit): array;
}
