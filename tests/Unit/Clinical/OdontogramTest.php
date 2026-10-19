<?php

declare(strict_types=1);

use Src\Clinical\Domain\Dentition;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Clinical\Domain\Odontogram;
use Src\Clinical\Domain\ProcedureTarget;
use Src\Clinical\Domain\Surface;
use Src\Clinical\Domain\Tooth;
use Src\Clinical\Domain\ToothStateEntry;
use Src\Clinical\Domain\Vitals;

function entri(string $id, int $fdi, string $surface, string $code, string $at, string $encounter = 'E1'): ToothStateEntry
{
    return new ToothStateEntry($id, $fdi, Surface::from($surface), $code, new DateTimeImmutable($at), $encounter);
}

it('ODO-01 nomor FDI: 32 gigi dewasa dan 20 gigi sulung', function () {
    expect(Tooth::all(Dentition::Permanent))->toHaveCount(32)
        ->and(Tooth::all(Dentition::Primary))->toHaveCount(20)
        ->and(Tooth::all(Dentition::Mixed))->toHaveCount(52);
    foreach (Tooth::all(Dentition::Mixed) as $fdi) {
        expect((new Tooth($fdi))->fdi)->toBe($fdi);
    }
});

it('ODO-01 nomor gigi di luar FDI ditolak', function () {
    foreach ([0, 9, 10, 19, 29, 49, 56, 66, 76, 86, 99, 111] as $salah) {
        expect(fn () => new Tooth($salah))->toThrow(InvalidClinicalData::class);
    }
});

it('ODO-01 dentisi, rahang, dan gigi depan dikenali dari nomor FDI', function () {
    expect((new Tooth(16))->dentition())->toBe(Dentition::Permanent)
        ->and((new Tooth(54))->dentition())->toBe(Dentition::Primary)
        ->and((new Tooth(16))->isUpper())->toBeTrue()
        ->and((new Tooth(46))->isUpper())->toBeFalse()
        ->and((new Tooth(63))->isUpper())->toBeTrue()
        ->and((new Tooth(11))->isAnterior())->toBeTrue()
        ->and((new Tooth(14))->isAnterior())->toBeFalse();
});

it('ODO-02 lima permukaan ditambah seluruh gigi', function () {
    expect(array_map(fn (Surface $s) => $s->value, Surface::faces()))->toBe(['M', 'D', 'O', 'B', 'L'])
        ->and(Surface::Whole->value)->toBe('W');
});

it('ODO-04 keadaan terkini mengambil entri terbaru per gigi dan permukaan lintas snapshot', function () {
    $hasil = Odontogram::current([
        entri('A', 16, 'O', 'KAR', '2025-01-10 09:00:00', 'E1'),
        entri('B', 16, 'O', 'TAM', '2025-03-10 09:00:00', 'E2'),
        entri('C', 16, 'M', 'KAR', '2025-01-10 09:00:00', 'E1'),
        entri('D', 46, 'O', 'KAR', '2025-02-01 09:00:00', 'E1'),
    ]);

    expect($hasil[16]['surfaces']['O']->conditionCode)->toBe('TAM')
        ->and($hasil[16]['surfaces']['M']->conditionCode)->toBe('KAR')
        ->and($hasil[16]['surfaces']['O']->encounterId)->toBe('E2')
        ->and($hasil[46]['surfaces']['O']->conditionCode)->toBe('KAR')
        ->and($hasil[16]['whole'])->toBeNull()
        ->and(array_keys($hasil))->toBe([16, 46]);
});

it('ODO-04 kondisi seluruh gigi menimpa permukaan yang lebih lama, bukan yang lebih baru', function () {
    $hasil = Odontogram::current([
        entri('A', 36, 'O', 'KAR', '2025-01-10 09:00:00'),
        entri('B', 36, 'M', 'TAM', '2025-01-10 09:05:00'),
        entri('C', 36, 'W', 'HLG', '2025-02-10 09:00:00'),
        entri('D', 36, 'D', 'KAR', '2025-03-10 09:00:00'),
    ]);

    expect($hasil[36]['whole']?->conditionCode)->toBe('HLG')
        ->and(array_keys($hasil[36]['surfaces']))->toBe(['D']);
});

it('ODO-04 urutan masukan tidak memengaruhi hasil', function () {
    $a = entri('A', 11, 'O', 'KAR', '2025-01-10 09:00:00');
    $b = entri('B', 11, 'O', 'TAM', '2025-02-10 09:00:00');

    expect(Odontogram::current([$a, $b])[11]['surfaces']['O']->conditionCode)->toBe('TAM')
        ->and(Odontogram::current([$b, $a])[11]['surfaces']['O']->conditionCode)->toBe('TAM');
});

it('ODO-01 dentisi snapshot mengikuti gigi yang tercatat', function () {
    expect(Odontogram::dentitionOf([16, 26]))->toBe(Dentition::Permanent)
        ->and(Odontogram::dentitionOf([54, 64]))->toBe(Dentition::Primary)
        ->and(Odontogram::dentitionOf([16, 54]))->toBe(Dentition::Mixed);
});

it('RM-05 sasaran tindakan per permukaan mengurutkan dan membuang duplikat', function () {
    $t = ProcedureTarget::for('surface', 16, ['O', 'M', 'O'], null);

    expect($t->surfaceCodes())->toBe('OM')->and($t->tooth?->fdi)->toBe(16);
    expect(ProcedureTarget::for('visit', 16, ['O'], 'upper')->tooth)->toBeNull();
});

it('RM-02 tanda vital: rentang, pasangan tekanan darah, dan suhu dalam persepuluh derajat', function () {
    expect((new Vitals(120, 80, 70, 368))->toArray())->toBe(['systolic' => 120, 'diastolic' => 80, 'pulse' => 70, 'temperature_tenths' => 368]);
    expect(fn () => new Vitals(120, null))->toThrow(InvalidClinicalData::class)
        ->and(fn () => new Vitals(80, 120))->toThrow(InvalidClinicalData::class)
        ->and(fn () => new Vitals(null, null, 400))->toThrow(InvalidClinicalData::class)
        ->and(fn () => new Vitals(null, null, null, 290))->toThrow(InvalidClinicalData::class);
    expect(Vitals::fromArray(null)->toArray())->toBe(['systolic' => null, 'diastolic' => null, 'pulse' => null, 'temperature_tenths' => null]);
});
