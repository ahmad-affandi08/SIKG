<?php

declare(strict_types=1);

use Src\Reporting\Domain\DoctorFeeScheme;

/** @return list<array{code: string, name: string, qty: int, base: int}> */
function tindakanUji(array $baris): array
{
    return array_map(fn (array $b) => ['code' => $b[0], 'name' => 'Tindakan '.$b[0], 'qty' => $b[1], 'base' => $b[2]], $baris);
}

it('P2-09 tanpa skema jasa dokter nol dan semua hitungan bilangan bulat', function () {
    $m = DoctorFeeScheme::fromArray(null)->month('2025-06', tindakanUji([['CABUT', 1, 150000]]), 100000);

    expect($m->gross)->toBe(0)->and($m->net)->toBe(0)->and($m->labDeduction)->toBe(0)->and($m->base)->toBe(150000);
});

it('P2-09 skema lama (bawaan dan pengecualian) tetap sama: persentase dan nominal tetap per tindakan', function () {
    $skema = DoctorFeeScheme::fromArray(['default' => ['mode' => 'percentage', 'value' => 4000], 'overrides' => ['TAMBAL' => ['mode' => 'fixed', 'value' => 50000]]]);

    $m = $skema->month('2025-06', tindakanUji([['CABUT', 1, 150000], ['TAMBAL', 2, 200000]]), 0);

    expect($m->gross)->toBe(160000)->and($m->net)->toBe(160000)->and(array_column(array_map(fn ($l) => ['c' => $l->code, 'f' => $l->fee], $m->lines), 'f', 'c'))->toBe(['CABUT' => 60000, 'TAMBAL' => 100000]);
});

it('P2-09 jasa bertingkat marginal: tiap tingkat berlaku untuk bagian produksi di dalam batasnya', function () {
    $skema = DoctorFeeScheme::fromArray([
        'default' => ['mode' => 'percentage', 'value' => 1000],
        'overrides' => [],
        'tiers' => [['up_to' => 10000000, 'rate_bp' => 3000], ['up_to' => 25000000, 'rate_bp' => 3500], ['up_to' => null, 'rate_bp' => 4000]],
    ]);

    // 10 jt x 30% + 15 jt x 35% + 5 jt x 40% = 3.000.000 + 5.250.000 + 2.000.000
    $atas = $skema->month('2025-06', tindakanUji([['A', 1, 30000000]]), 0);
    $bawah = $skema->month('2025-06', tindakanUji([['A', 1, 4000000]]), 0);
    $tepi = $skema->month('2025-06', tindakanUji([['A', 1, 10000000]]), 0);

    expect($atas->gross)->toBe(10250000)->and($bawah->gross)->toBe(1200000)->and($tepi->gross)->toBe(3000000);
});

it('P2-09 jasa bertingkat dibagi ke baris sesuai porsi dan jumlahnya sama dengan total tingkat', function () {
    $skema = DoctorFeeScheme::fromArray(['default' => ['mode' => 'percentage', 'value' => 0], 'tiers' => [['up_to' => null, 'rate_bp' => 3333]]]);

    $m = $skema->month('2025-06', tindakanUji([['A', 1, 100001], ['B', 1, 200003], ['C', 1, 50007]]), 0);

    expect(array_sum(array_map(fn ($l) => $l->fee, $m->lines)))->toBe($m->gross)->and($m->gross)->toBe(intdiv(350011 * 3333, 10000))
        ->and(array_unique(array_map(fn ($l) => $l->rule, $m->lines)))->toBe(['Bertingkat']);
});

it('P2-09 pengecualian per kode tidak ikut bertingkat dan produksinya tidak menaikkan tingkat', function () {
    $skema = DoctorFeeScheme::fromArray([
        'default' => ['mode' => 'percentage', 'value' => 0],
        'overrides' => ['ORTO' => ['mode' => 'fixed', 'value' => 100000]],
        'tiers' => [['up_to' => 1000000, 'rate_bp' => 1000], ['up_to' => null, 'rate_bp' => 5000]],
    ]);

    $m = $skema->month('2025-06', tindakanUji([['ORTO', 3, 90000000], ['TAMBAL', 1, 2000000]]), 0);

    // ORTO 3 x 100.000; TAMBAL 1 jt x 10% + 1 jt x 50%
    expect($m->gross)->toBe(300000 + 100000 + 500000);
});

it('P2-09 potongan biaya lab sebesar bagian yang dibebankan, jasa bersih tidak negatif, kelebihan dicatat', function () {
    $setengah = DoctorFeeScheme::fromArray(['default' => ['mode' => 'percentage', 'value' => 5000], 'lab_share_bp' => 5000]);
    $penuh = DoctorFeeScheme::fromArray(['default' => ['mode' => 'percentage', 'value' => 5000], 'lab_share_bp' => 10000]);

    $a = $setengah->month('2025-06', tindakanUji([['A', 1, 1000000]]), 300000);
    $b = $penuh->month('2025-06', tindakanUji([['A', 1, 1000000]]), 300000);
    $c = $penuh->month('2025-06', tindakanUji([['A', 1, 100000]]), 300000);
    $d = $penuh->month('2025-06', [], 300000);

    expect([$a->gross, $a->labDeduction, $a->net, $a->uncovered])->toBe([500000, 150000, 350000, 0])
        ->and([$b->labDeduction, $b->net])->toBe([300000, 200000])
        ->and([$c->gross, $c->labDeduction, $c->net, $c->uncovered])->toBe([50000, 300000, 0, 250000])
        ->and([$d->gross, $d->net, $d->uncovered])->toBe([0, 0, 300000]);
});

it('P2-09 ringkasan skema menyebut tingkat, pengecualian, dan beban biaya lab', function () {
    $teks = DoctorFeeScheme::fromArray([
        'default' => ['mode' => 'percentage', 'value' => 3000], 'overrides' => ['A' => ['mode' => 'fixed', 'value' => 1000]],
        'tiers' => [['up_to' => 10000000, 'rate_bp' => 3000], ['up_to' => null, 'rate_bp' => 3500]], 'lab_share_bp' => 5000,
    ])->describe();

    expect($teks)->toContain('Bertingkat per bulan')->toContain('Rp 0 sampai Rp 10.000.000 = 30%')->toContain('di atas Rp 10.000.000 = 35%')->toContain('pengecualian 1 kode tindakan')->toContain('beban biaya lab 50%');
    expect(DoctorFeeScheme::fromArray(['default' => ['mode' => 'fixed', 'value' => 25000]])->describe())->toBe('Aturan bawaan Rp 25.000 per tindakan');
});
