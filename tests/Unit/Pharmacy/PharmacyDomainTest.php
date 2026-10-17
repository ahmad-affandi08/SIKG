<?php

declare(strict_types=1);

use Src\Pharmacy\Domain\AllergyMatcher;
use Src\Pharmacy\Domain\InvalidPharmacyData;
use Src\Pharmacy\Domain\PrescriptionItemInput;

it('OBT-02 kelas obat dicocokkan dengan nama alergi tanpa memedulikan huruf dan garis bawah', function () {
    expect(AllergyMatcher::matches('penisilin', 'Penisilin'))->toBeTrue()
        ->and(AllergyMatcher::matches('penisilin', 'Alergi obat golongan penisilin'))->toBeTrue()
        ->and(AllergyMatcher::matches('anestesi_lokal', 'Anestesi lokal (lidokain)'))->toBeTrue()
        ->and(AllergyMatcher::matches('nsaid', 'Penisilin'))->toBeFalse()
        ->and(AllergyMatcher::matches(null, 'Penisilin'))->toBeFalse()
        ->and(AllergyMatcher::matches('  ', 'Penisilin'))->toBeFalse()
        ->and(AllergyMatcher::matches('penisilin', ''))->toBeFalse();
});

it('OBT-01 baris resep: katalog atau teks bebas, salah satu, dan data wajib lengkap', function () {
    $ok = new PrescriptionItemInput('01JABCDEFGHJKMNPQRSTVWXYZ0', null, '500 mg', '3 x sehari', '5 hari', 'Sesudah makan', 15, true);
    expect($ok->quantity)->toBe(15)->and($ok->dispenseInClinic)->toBeTrue();

    expect(fn () => new PrescriptionItemInput(null, null, '1', '1', '1', null, 1, false))->toThrow(InvalidPharmacyData::class)
        ->and(fn () => new PrescriptionItemInput('01JABCDEFGHJKMNPQRSTVWXYZ0', 'Obat X', '1', '1', '1', null, 1, false))->toThrow(InvalidPharmacyData::class)
        ->and(fn () => new PrescriptionItemInput(null, 'Obat bebas', '1', '1', '1', null, 1, true))->toThrow(InvalidPharmacyData::class)
        ->and(fn () => new PrescriptionItemInput(null, 'Obat bebas', '', '1', '1', null, 1, false))->toThrow(InvalidPharmacyData::class)
        ->and(fn () => new PrescriptionItemInput(null, 'Obat bebas', '1', '1', '1', null, 0, false))->toThrow(InvalidPharmacyData::class)
        ->and(fn () => new PrescriptionItemInput(null, 'Obat bebas', '1', '1', '1', null, 10000, false))->toThrow(InvalidPharmacyData::class);
});
