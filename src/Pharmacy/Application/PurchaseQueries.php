<?php

declare(strict_types=1);

namespace Src\Pharmacy\Application;

use Src\Pharmacy\Contracts\MedicineCatalog;
use Src\Pharmacy\Domain\InvalidPharmacyData;
use Src\Pharmacy\Domain\PurchaseOrder;
use Src\Pharmacy\Domain\PurchaseOrderRepository;
use Src\Pharmacy\Domain\PurchaseOrderStatus;
use Src\Pharmacy\Domain\StockRepository;
use Src\Pharmacy\Domain\SupplierDirectory;

/**
 * P2-05: bacaan untuk halaman pembelian dan batch. Hanya baca; izin dicek di Presentation.
 */
final readonly class PurchaseQueries
{
    private const LIST_LIMIT = 100;

    public function __construct(
        private PurchaseOrderRepository $orders,
        private SupplierDirectory $suppliers,
        private MedicineCatalog $medicines,
        private StockRepository $stock,
    ) {}

    /**
     * @return array{orders: list<array<string, mixed>>, statuses: list<array{value: string, label: string}>}
     */
    public function list(?string $status): array
    {
        $filter = $status === null || $status === '' ? null : PurchaseOrderStatus::tryFrom($status);
        $orders = $this->orders->recent($filter, self::LIST_LIMIT);
        $names = $this->suppliers->names(array_values(array_unique(array_map(fn (PurchaseOrder $o) => $o->supplierId, $orders))));

        return [
            'orders' => array_map(fn (PurchaseOrder $o) => $this->row($o, $names[$o->supplierId] ?? '-'), $orders),
            'statuses' => array_map(fn (PurchaseOrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], PurchaseOrderStatus::cases()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string $id): array
    {
        $order = $this->orders->find($id) ?? throw new InvalidPharmacyData('purchase.not_found', 'Pesanan pembelian tidak ditemukan.');
        $supplier = $this->suppliers->names([$order->supplierId])[$order->supplierId] ?? '-';

        $items = [];
        foreach ($order->items() as $item) {
            $medicine = $this->medicines->find($item->medicineId);
            $items[] = [
                'id' => $item->id,
                'medicine_id' => $item->medicineId,
                'name' => $medicine->name ?? '-',
                'unit' => $medicine->unit ?? '',
                'quantity_ordered' => $item->quantityOrdered,
                'quantity_received' => $item->received(),
                'outstanding' => $item->outstanding(),
                'unit_cost_amount' => $item->unitCostAmount,
                'line_total' => $item->lineTotal()->amount,
            ];
        }

        return [...$this->row($order, $supplier), 'note' => $order->note, 'items' => $items];
    }

    /**
     * @return array{suppliers: list<array{value: string, label: string}>, medicines: list<array{value: string, label: string}>}
     */
    public function createForm(): array
    {
        $medicines = [];
        foreach ($this->stock->overview() as $row) {
            if ($row['is_active']) {
                $medicines[] = ['value' => $row['id'], 'label' => $row['name'].' ('.$row['unit'].')'];
            }
        }

        return ['suppliers' => $this->suppliers->options(), 'medicines' => $medicines];
    }

    /**
     * Batch yang masih bersisa, kedaluwarsa terdekat lebih dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function batches(): array
    {
        $batches = $this->stock->batches(null);
        $names = $this->suppliers->names(array_values(array_unique(array_filter(array_column($batches, 'supplier_id')))));

        return array_map(fn (array $b) => [...$b, 'supplier_name' => $b['supplier_id'] === null ? null : ($names[$b['supplier_id']] ?? null)], $batches);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(PurchaseOrder $order, string $supplier): array
    {
        return [
            'id' => $order->id,
            'po_no' => $order->poNo,
            'supplier_id' => $order->supplierId,
            'supplier_name' => $supplier,
            'status' => $order->status()->value,
            'status_label' => $order->status()->label(),
            'expected_date' => $order->expectedDate,
            'ordered_at' => $order->orderedAt()?->format('c'),
            'total_amount' => $order->total()->amount,
            'item_count' => count($order->items()),
        ];
    }
}
