<?php

declare(strict_types=1);

use Src\Lab\Domain\InvalidLabOrder;
use Src\Lab\Domain\LabOrder;
use Src\Lab\Domain\LabOrderStatus;
use Src\Lab\Domain\WorkType;

function pesananLabUji(array $o = []): LabOrder
{
    return LabOrder::place('O1', 'LAB-2025-00001', 'P1', 'D1', 'L1', WorkType::Crown, '46', 'Mahkota zirkonia', ' A2 ', $o['ordered'] ?? '2025-06-16', $o['due'] ?? '2025-06-30', $o['cost'] ?? 450000, $o['charge'] ?? 1200000);
}

it('P2-06 pesanan baru berstatus dipesan dengan uang rupiah bulat dan warna dirapikan', function () {
    $o = pesananLabUji();

    expect($o->status())->toBe(LabOrderStatus::Ordered)->and($o->labCostAmount())->toBe(450000)->and($o->patientChargeAmount())->toBe(1200000)
        ->and($o->shade)->toBe('A2')->and($o->isBilled())->toBeFalse();
});

it('P2-06 pesanan menolak uraian kosong, uang negatif, dan target sebelum tanggal pesan', function () {
    $buat = fn (string $uraian, int $biaya, ?string $target) => fn () => LabOrder::place('O', 'N', 'P', 'D', 'L', WorkType::Other, '', $uraian, null, '2025-06-16', $target, $biaya, 0);

    expect($buat(' ', 0, null))->toThrow(InvalidLabOrder::class, 'Uraian pekerjaan wajib')
        ->and($buat('x', -1, null))->toThrow(InvalidLabOrder::class, 'tidak negatif')
        ->and($buat('x', 0, '2025-06-01'))->toThrow(InvalidLabOrder::class, 'Target selesai');
});

it('P2-06 alur dipesan, dikirim, diterima dengan biaya aktual dan tanggal yang berurutan', function () {
    $o = pesananLabUji();

    expect(fn () => $o->receive('2025-06-20', 1))->toThrow(InvalidLabOrder::class, 'dikirim ke lab dulu');
    expect(fn () => $o->send('2025-06-15'))->toThrow(InvalidLabOrder::class, 'sebelum tanggal pesan');

    $o->send('2025-06-17');
    expect($o->status())->toBe(LabOrderStatus::Sent)->and(fn () => $o->send('2025-06-18'))->toThrow(InvalidLabOrder::class);
    expect(fn () => $o->receive('2025-06-16', 1))->toThrow(InvalidLabOrder::class, 'sebelum tanggal kirim');

    $o->receive('2025-06-25', 500000);
    expect($o->status())->toBe(LabOrderStatus::Received)->and($o->labCostAmount())->toBe(500000)->and($o->patientChargeAmount())->toBe(1200000)->and($o->receivedDate())->toBe('2025-06-25');
});

it('P2-06 pembatalan butuh alasan dan hanya sebelum diterima', function () {
    $o = pesananLabUji();
    expect(fn () => $o->cancel(' '))->toThrow(InvalidLabOrder::class, 'Alasan pembatalan');

    $o->cancel('Pasien batal');
    expect($o->status())->toBe(LabOrderStatus::Cancelled)->and($o->cancelNote())->toBe('Pasien batal')->and(fn () => $o->cancel('lagi'))->toThrow(InvalidLabOrder::class);

    $diterima = pesananLabUji();
    $diterima->send('2025-06-17');
    $diterima->receive('2025-06-20', 1);
    expect(fn () => $diterima->cancel('x'))->toThrow(InvalidLabOrder::class, 'tidak dapat dibatalkan');
});

it('P2-06 penagihan hanya setelah diterima, sekali, dan harga ke pasien harus lebih dari nol', function () {
    $o = pesananLabUji();
    expect(fn () => $o->markBilled('INV1'))->toThrow(InvalidLabOrder::class, 'setelah pekerjaan diterima');

    $o->send('2025-06-17');
    $o->receive('2025-06-20', 450000);
    $o->markBilled('INV1');
    expect($o->isBilled())->toBeTrue()->and($o->invoiceId())->toBe('INV1')->and(fn () => $o->markBilled('INV2'))->toThrow(InvalidLabOrder::class, 'sudah ditagihkan');

    $gratis = pesananLabUji(['charge' => 0]);
    $gratis->send('2025-06-17');
    $gratis->receive('2025-06-20', 450000);
    expect(fn () => $gratis->markBilled('INV3'))->toThrow(InvalidLabOrder::class, 'nol');
});
