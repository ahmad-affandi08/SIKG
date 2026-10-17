<?php

declare(strict_types=1);

use Src\Pharmacy\Domain\InvalidPharmacyData;
use Src\Pharmacy\Domain\PurchaseOrder;
use Src\Pharmacy\Domain\PurchaseOrderItem;
use Src\Pharmacy\Domain\PurchaseOrderStatus;
use Src\Pharmacy\Domain\StockCountPolicy;

function pesananUji(): PurchaseOrder
{
    return PurchaseOrder::draft('PO1', 'PO-2025-00001', 'SUP1', null, null, [
        new PurchaseOrderItem('I1', 'M1', 10, 0, 2500),
        new PurchaseOrderItem('I2', 'M2', 4, 0, 10000),
    ]);
}

it('P2-05 total pesanan dihitung dari harga beli dikali jumlah dalam rupiah bulat', function () {
    expect(pesananUji()->total()->amount)->toBe(65000)->and(pesananUji()->status())->toBe(PurchaseOrderStatus::Draft);
});

it('P2-05 pesanan harus berisi obat, jumlah positif, harga tidak negatif, dan obat unik', function () {
    $bentuk = fn (array $items) => fn () => PurchaseOrder::draft('PO1', 'PO-1', 'S', null, null, $items);

    expect($bentuk([]))->toThrow(InvalidPharmacyData::class, 'minimal memuat satu obat')
        ->and($bentuk([new PurchaseOrderItem('I', 'M', 0, 0, 100)]))->toThrow(InvalidPharmacyData::class, 'bilangan bulat positif')
        ->and($bentuk([new PurchaseOrderItem('I', 'M', 1, 0, -1)]))->toThrow(InvalidPharmacyData::class, 'tidak boleh negatif')
        ->and($bentuk([new PurchaseOrderItem('I', 'M', 1, 0, 1), new PurchaseOrderItem('J', 'M', 1, 0, 1)]))->toThrow(InvalidPharmacyData::class, 'hanya boleh muncul sekali');
});

it('P2-05 status diturunkan dari penerimaan: dipesan, sebagian, lengkap', function () {
    $po = pesananUji();
    expect(fn () => $po->receive('I1', 1))->toThrow(InvalidPharmacyData::class, 'harus berstatus dipesan');

    $po->markOrdered(new DateTimeImmutable('2025-06-16 07:00:00'));
    expect($po->status())->toBe(PurchaseOrderStatus::Ordered);

    $po->receive('I1', 10);
    expect($po->status())->toBe(PurchaseOrderStatus::Partial);

    $po->receive('I2', 4);
    expect($po->status())->toBe(PurchaseOrderStatus::Received)->and($po->items()[0]->outstanding())->toBe(0);
});

it('P2-05 penerimaan tidak boleh melebihi sisa pesanan dan pesanan yang sudah diterima tidak dapat dibatalkan', function () {
    $po = pesananUji();
    $po->markOrdered(new DateTimeImmutable('2025-06-16 07:00:00'));

    expect(fn () => $po->receive('I1', 11))->toThrow(InvalidPharmacyData::class, 'sisa pesanan (10)')
        ->and(fn () => $po->receive('I1', 0))->toThrow(InvalidPharmacyData::class)
        ->and(fn () => $po->receive('NADA', 1))->toThrow(InvalidPharmacyData::class, 'tidak ditemukan');

    $po->receive('I1', 3);
    expect(fn () => $po->cancel())->toThrow(InvalidPharmacyData::class, 'tidak dapat dibatalkan');
});

it('P2-05 draft dan pesanan yang belum menerima barang dapat dibatalkan', function () {
    $draft = pesananUji();
    $draft->cancel();
    $dipesan = pesananUji();
    $dipesan->markOrdered(new DateTimeImmutable('2025-06-16 07:00:00'));
    $dipesan->cancel();

    expect($draft->status())->toBe(PurchaseOrderStatus::Cancelled)->and($dipesan->status())->toBe(PurchaseOrderStatus::Cancelled)
        ->and(fn () => $dipesan->markOrdered(new DateTimeImmutable('2025-06-17 07:00:00')))->toThrow(InvalidPharmacyData::class, 'draft');
});

it('P2-05 jadwal opname: interval 7 sampai 365 hari dan jatuh tempo dihitung dari hari sesi dibuat', function () {
    expect(StockCountPolicy::validInterval(6))->toBeFalse()->and(StockCountPolicy::validInterval(7))->toBeTrue()->and(StockCountPolicy::validInterval(365))->toBeTrue()->and(StockCountPolicy::validInterval(366))->toBeFalse()
        ->and(StockCountPolicy::nextDue('2025-06-16', 30))->toBe('2025-07-16')
        ->and(StockCountPolicy::isDue(null, '2025-06-16'))->toBeTrue()->and(StockCountPolicy::isDue('2025-06-16', '2025-06-16'))->toBeTrue()->and(StockCountPolicy::isDue('2025-06-17', '2025-06-16'))->toBeFalse();
});
