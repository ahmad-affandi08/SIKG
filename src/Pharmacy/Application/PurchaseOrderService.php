<?php

declare(strict_types=1);

namespace Src\Pharmacy\Application;

use DateTimeImmutable;
use Src\Pharmacy\Domain\InvalidPharmacyData;
use Src\Pharmacy\Domain\PurchaseOrder;
use Src\Pharmacy\Domain\PurchaseOrderItem;
use Src\Pharmacy\Domain\PurchaseOrderRepository;
use Src\Pharmacy\Domain\SupplierDirectory;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

/**
 * P2-05: pembelian ke pemasok. Pesanan dibuat sebagai draft, dipesan, lalu barang diterima bertahap; tiap penerimaan membuat
 * batch (nomor batch, kedaluwarsa, harga beli, pemasok) lewat StockService dalam transaksi yang sama. Pesanan dikunci selama
 * penerimaan agar dua penerimaan bersamaan tidak melebihi jumlah pesanan. Izin dicek di Presentation (M9).
 */
final readonly class PurchaseOrderService
{
    public function __construct(
        private PurchaseOrderRepository $orders,
        private SupplierDirectory $suppliers,
        private MedicineChecker $medicines,
        private StockService $stock,
        private PurchaseOrderNumbering $numbering,
        private Transaction $tx,
        private AuditLogger $audit,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    /**
     * @param  list<array{medicine_id: string, quantity: int, unit_cost_amount: int}>  $lines
     */
    public function create(string $actorId, string $supplierId, ?string $expectedDate, ?string $note, array $lines): string
    {
        return $this->tx->run(function () use ($actorId, $supplierId, $expectedDate, $note, $lines): string {
            if (! $this->suppliers->isActive($supplierId)) {
                throw new InvalidPharmacyData('purchase.supplier_not_found', 'Pemasok tidak ditemukan atau nonaktif.');
            }
            $expected = $expectedDate === null || $expectedDate === '' ? null : $this->date($expectedDate);
            $items = [];
            foreach ($lines as $line) {
                $this->medicines->assertExists($line['medicine_id']);
                $items[] = new PurchaseOrderItem($this->ids->generate(), $line['medicine_id'], $line['quantity'], 0, $line['unit_cost_amount']);
            }

            $order = PurchaseOrder::draft($this->ids->generate(), $this->numbering->next(), $supplierId, $expected, $this->text($note), $items);
            $this->orders->add($order, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'purchase.created', 'purchase_order', $order->id, null, ['po_no' => $order->poNo, 'supplier_id' => $supplierId, 'total' => $order->total()->amount, 'items' => count($items)]));

            return $order->id;
        });
    }

    public function order(string $actorId, string $orderId): void
    {
        $this->change($actorId, $orderId, 'purchase.ordered', function (PurchaseOrder $order): void {
            $order->markOrdered($this->clock->now());
        });
    }

    public function cancel(string $actorId, string $orderId): void
    {
        $this->change($actorId, $orderId, 'purchase.cancelled', function (PurchaseOrder $order): void {
            $order->cancel();
        });
    }

    /**
     * @param  list<array{item_id: string, quantity: int, batch_no: ?string, expiry_date: ?string}>  $lines  baris dengan jumlah nol diabaikan
     */
    public function receive(string $actorId, string $orderId, array $lines): void
    {
        $lines = array_values(array_filter($lines, fn (array $l): bool => $l['quantity'] > 0));
        if ($lines === []) {
            throw new InvalidPharmacyData('purchase.nothing_received', 'Isi jumlah diterima pada minimal satu baris.');
        }

        $this->tx->run(function () use ($actorId, $orderId, $lines): void {
            $order = $this->orders->lock($orderId) ?? throw new InvalidPharmacyData('purchase.not_found', 'Pesanan pembelian tidak ditemukan.');
            $before = $order->status()->value;
            $seen = [];
            foreach ($lines as $line) {
                if (isset($seen[$line['item_id']])) {
                    throw new InvalidPharmacyData('purchase.duplicate_line', 'Satu baris pesanan hanya boleh diterima sekali per penerimaan.');
                }
                $seen[$line['item_id']] = true;
                $item = $order->receive($line['item_id'], $line['quantity']);
                $this->stock->receiveFromPurchase($actorId, $item->medicineId, $line['quantity'], $line['batch_no'], $line['expiry_date'], $item->unitCostAmount, $order->supplierId, $order->id, $order->poNo);
            }
            $this->orders->save($order, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'purchase.received', 'purchase_order', $order->id, ['status' => $before], ['status' => $order->status()->value, 'lines' => count($lines)]));
        });
    }

    /**
     * @param  non-empty-string  $action
     * @param  callable(PurchaseOrder): void  $apply
     */
    private function change(string $actorId, string $orderId, string $action, callable $apply): void
    {
        $this->tx->run(function () use ($actorId, $orderId, $action, $apply): void {
            $order = $this->orders->lock($orderId) ?? throw new InvalidPharmacyData('purchase.not_found', 'Pesanan pembelian tidak ditemukan.');
            $before = $order->status()->value;
            $apply($order);
            $this->orders->save($order, $actorId);
            $this->audit->record(new AuditEntry($actorId, $action, 'purchase_order', $order->id, ['status' => $before], ['status' => $order->status()->value]));
        });
    }

    private function text(?string $text): ?string
    {
        return $text === null || trim($text) === '' ? null : mb_substr(trim($text), 0, 255);
    }

    private function date(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidPharmacyData('purchase.invalid_date', 'Tanggal perkiraan tiba tidak valid.');
        }

        return $date;
    }
}
