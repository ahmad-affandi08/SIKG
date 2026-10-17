<?php

declare(strict_types=1);

use Src\Orthodontics\Domain\Appliance;
use Src\Orthodontics\Domain\CaseStatus;
use Src\Orthodontics\Domain\InstallmentSchedule;
use Src\Orthodontics\Domain\InvalidOrthodontics;
use Src\Orthodontics\Domain\OrthoCase;

function mulaiOrtho(): DateTimeImmutable
{
    return new DateTimeImmutable('2025-01-31', new DateTimeZone('UTC'));
}

function paketOrtho(int $biaya = 12000000): OrthoCase
{
    return OrthoCase::open('01HX0000000000000000000001', 'PAS1', 'DOK1', Appliance::MetalBraces, '  Behel logam  ', '  rencana  ', $biaya, '2025-01-31', 18);
}

it('P2-04 cicilan dibagi rata dan sisa rupiah jatuh pada cicilan terakhir', function () {
    $jadwal = InstallmentSchedule::generate(1000000, 3, 0, mulaiOrtho());

    expect(array_column($jadwal, 'amount'))->toBe([333333, 333333, 333334])->and(array_sum(array_column($jadwal, 'amount')))->toBe(1000000)
        ->and(array_column($jadwal, 'seq'))->toBe([1, 2, 3]);
});

it('P2-04 uang muka menjadi cicilan pertama dan sisanya dibagi rata', function () {
    $jadwal = InstallmentSchedule::generate(1000000, 4, 400000, mulaiOrtho());

    expect(array_column($jadwal, 'amount'))->toBe([400000, 200000, 200000, 200000]);
});

it('P2-04 jatuh tempo bulanan mengikuti tanggal mulai dan dipotong ke akhir bulan pendek', function () {
    $jadwal = InstallmentSchedule::generate(300000, 4, 0, mulaiOrtho());

    expect(array_column($jadwal, 'due_date'))->toBe(['2025-01-31', '2025-02-28', '2025-03-31', '2025-04-30']);
});

it('P2-04 satu cicilan sama dengan seluruh biaya dan uang muka penuh tidak butuh cicilan lain', function () {
    expect(array_column(InstallmentSchedule::generate(500000, 1, 0, mulaiOrtho()), 'amount'))->toBe([500000])
        ->and(array_column(InstallmentSchedule::generate(500000, 1, 500000, mulaiOrtho()), 'amount'))->toBe([500000])
        ->and(fn () => InstallmentSchedule::generate(500000, 3, 500000, mulaiOrtho()))->toThrow(InvalidOrthodontics::class);
});

it('P2-04 jadwal menolak biaya nol, jumlah cicilan di luar batas, uang muka berlebih, dan cicilan nol rupiah', function (int $biaya, int $jumlah, int $muka) {
    expect(fn () => InstallmentSchedule::generate($biaya, $jumlah, $muka, mulaiOrtho()))->toThrow(InvalidOrthodontics::class);
})->with([
    'biaya nol' => [0, 3, 0],
    'cicilan nol' => [100000, 0, 0],
    'cicilan terlalu banyak' => [100000, 37, 0],
    'uang muka melebihi biaya' => [100000, 3, 100001],
    'uang muka tanpa cicilan sisa' => [100000, 1, 40000],
    'terlalu kecil dibagi' => [2, 3, 0],
]);

it('P2-04 paket baru berjalan, nama dirapikan, dan biaya serta lama perawatan divalidasi', function () {
    $paket = paketOrtho();

    expect($paket->status())->toBe(CaseStatus::Active)->and($paket->title)->toBe('Behel logam')->and($paket->planNotes)->toBe('rencana')->and($paket->isActive())->toBeTrue();
    expect(fn () => OrthoCase::open('X', 'P', 'D', Appliance::Aligner, ' ', null, 1000, '2025-01-01', 12))->toThrow(InvalidOrthodontics::class, 'Nama paket');
    expect(fn () => OrthoCase::open('X', 'P', 'D', Appliance::Aligner, 'A', null, 0, '2025-01-01', 12))->toThrow(InvalidOrthodontics::class, 'Biaya');
    expect(fn () => OrthoCase::open('X', 'P', 'D', Appliance::Aligner, 'A', null, 1000, '2025-01-01', 61))->toThrow(InvalidOrthodontics::class, 'lama');
});

it('P2-04 menyelesaikan atau menghentikan paket menutupnya, penghentian butuh alasan', function () {
    $selesai = paketOrtho();
    $selesai->complete(new DateTimeImmutable('2025-12-01'), '  rapi  ');
    expect($selesai->status())->toBe(CaseStatus::Completed)->and($selesai->endedNote())->toBe('rapi')->and($selesai->version())->toBe(2)
        ->and(fn () => $selesai->assertActive())->toThrow(InvalidOrthodontics::class, 'selesai');

    $henti = paketOrtho();
    expect(fn () => $henti->stop(new DateTimeImmutable('2025-12-01'), ' '))->toThrow(InvalidOrthodontics::class, 'Alasan');
    $henti->stop(new DateTimeImmutable('2025-12-01'), 'Pasien pindah kota');
    expect($henti->status())->toBe(CaseStatus::Stopped)->and($henti->endedNote())->toBe('Pasien pindah kota')
        ->and(fn () => $henti->stop(new DateTimeImmutable('2025-12-02'), 'lagi'))->toThrow(InvalidOrthodontics::class);
});

it('P2-04 label kontrol menyesuaikan jenis alat', function () {
    expect(Appliance::Aligner->controlLabel())->toBe('Tray aligner')->and(Appliance::MetalBraces->controlLabel())->toBe('Kawat atau komponen')
        ->and(Appliance::CeramicBraces->label())->toBe('Behel keramik');
});
