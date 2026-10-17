<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure;

use DateTimeImmutable;
use Src\Pharmacy\Domain\PurchaseOrder;
use Src\Pharmacy\Domain\PurchaseOrderItem;
use Src\Pharmacy\Domain\PurchaseOrderRepository;
use Src\Pharmacy\Domain\PurchaseOrderStatus;
use Src\Pharmacy\Infrastructure\Eloquent\PurchaseOrderItemModel;
use Src\Pharmacy\Infrastructure\Eloquent\PurchaseOrderModel;

final class EloquentPurchaseOrderRepository implements PurchaseOrderRepository
{
    public function add(PurchaseOrder $order, string $actorId): void
    {
        $m = new PurchaseOrderModel;
        $m->id = $order->id;
        $m->forceFill($this->values($order) + ['po_no' => $order->poNo, 'supplier_id' => $order->supplierId, 'created_by' => $actorId])->save();
        foreach ($order->items() as $item) {
            $row = new PurchaseOrderItemModel;
            $row->id = $item->id;
            $row->forceFill(['purchase_order_id' => $order->id, 'medicine_id' => $item->medicineId, 'quantity_ordered' => $item->quantityOrdered, 'quantity_received' => $item->received(), 'unit_cost_amount' => $item->unitCostAmount])->save();
        }
    }

    public function lock(string $id): ?PurchaseOrder
    {
        $m = PurchaseOrderModel::query()->lockForUpdate()->find($id);

        return $m === null ? null : $this->order($m, true);
    }

    public function find(string $id): ?PurchaseOrder
    {
        $m = PurchaseOrderModel::query()->find($id);

        return $m === null ? null : $this->order($m, false);
    }

    public function save(PurchaseOrder $order, string $actorId): void
    {
        $m = PurchaseOrderModel::query()->findOrFail($order->id);
        $m->forceFill($this->values($order) + ['updated_by' => $actorId, 'version' => $m->version + 1])->save();
        foreach ($order->items() as $item) {
            PurchaseOrderItemModel::query()->whereKey($item->id)->update(['quantity_received' => $item->received()]);
        }
    }

    public function recent(?PurchaseOrderStatus $status, int $limit): array
    {
        $statusValue = $status?->value;

        return array_values(PurchaseOrderModel::query()
            ->when($statusValue !== null, fn ($q) => $q->where('status', $statusValue))
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (PurchaseOrderModel $m) => $this->order($m, false))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function values(PurchaseOrder $order): array
    {
        return ['status' => $order->status()->value, 'ordered_at' => $order->orderedAt(), 'expected_date' => $order->expectedDate, 'note' => $order->note];
    }

    private function order(PurchaseOrderModel $m, bool $lock): PurchaseOrder
    {
        $items = PurchaseOrderItemModel::query()->where('purchase_order_id', $m->id)->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get()
            ->map(fn (PurchaseOrderItemModel $i) => new PurchaseOrderItem($i->id, $i->medicine_id, $i->quantity_ordered, $i->quantity_received, $i->unit_cost_amount))->all();

        return PurchaseOrder::restore(
            $m->id,
            $m->po_no,
            $m->supplier_id,
            PurchaseOrderStatus::from($m->status),
            $m->expected_date?->toDateString(),
            $m->note,
            $m->ordered_at === null ? null : DateTimeImmutable::createFromInterface($m->ordered_at),
            array_values($items),
            $m->version,
        );
    }
}
