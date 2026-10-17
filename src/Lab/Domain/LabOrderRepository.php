<?php

declare(strict_types=1);

namespace Src\Lab\Domain;

interface LabOrderRepository
{
    public function add(LabOrder $order, string $actorId): void;

    public function find(string $id): ?LabOrder;

    /**
     * Pesanan dikunci selama transaksi pemanggil.
     */
    public function lock(string $id): ?LabOrder;

    public function save(LabOrder $order, string $actorId): void;

    /**
     * @return list<LabOrder> terbaru lebih dulu
     */
    public function list(?string $status, ?string $patientId, int $limit): array;
}
