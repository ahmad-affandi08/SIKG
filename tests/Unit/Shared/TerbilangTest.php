<?php

declare(strict_types=1);

use Src\Shared\Domain\Terbilang;

it('PRN terbilang rupiah menurut kaidah bahasa Indonesia', function (int $angka, string $kata) {
    expect(Terbilang::rupiah($angka))->toBe($kata);
})->with([
    [0, 'nol rupiah'],
    [1, 'satu rupiah'],
    [11, 'sebelas rupiah'],
    [15, 'lima belas rupiah'],
    [100, 'seratus rupiah'],
    [111, 'seratus sebelas rupiah'],
    [1000, 'seribu rupiah'],
    [1500, 'seribu lima ratus rupiah'],
    [2000, 'dua ribu rupiah'],
    [200000, 'dua ratus ribu rupiah'],
    [1250000, 'satu juta dua ratus lima puluh ribu rupiah'],
    [5000000, 'lima juta rupiah'],
    [12345678, 'dua belas juta tiga ratus empat puluh lima ribu enam ratus tujuh puluh delapan rupiah'],
    [1000000000, 'satu miliar rupiah'],
    [2000001000, 'dua miliar seribu rupiah'],
]);

it('PRN terbilang menolak bilangan negatif', function () {
    expect(fn () => Terbilang::rupiah(-1))->toThrow(InvalidArgumentException::class);
});
