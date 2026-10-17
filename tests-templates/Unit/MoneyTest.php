<?php

// TEMPLAT: salin ke tests/Unit/Shared/MoneyTest.php (sesuaikan namespace)

declare(strict_types=1);

use Src\Shared\Domain\Money;

it('KSR-01 uang adalah bilangan bulat rupiah', function () {
    expect(Money::rupiah(1_250_000)->amount)->toBe(1_250_000);
});

it('KSR-01 menolak nilai negatif', function () {
    Money::rupiah(-1);
})->throws(InvalidArgumentException::class);

it('KSR-02 persentase dibulatkan di satu tempat (setengah ke atas)', function (int $amount, int $bp, int $expected) {
    expect(Money::rupiah($amount)->percent($bp)->amount)->toBe($expected);
})->with([
    [100_000, 1000, 10_000],   // 10%
    [333, 1000, 33],           // 33,3 -> 33
    [335, 1000, 34],           // 33,5 -> 34
    [1, 5000, 1],              // 0,5 -> 1
    [0, 1000, 0],
]);

it('KSR-01 penjumlahan dan pengurangan tidak kehilangan satuan', function () {
    $a = Money::rupiah(750_000);
    $b = Money::rupiah(250_001);
    expect($a->add($b)->amount)->toBe(1_000_001)
        ->and($a->subtract(Money::rupiah(1))->amount)->toBe(749_999);
});

it('KSR-01 pengurangan yang menghasilkan negatif ditolak', function () {
    Money::rupiah(1)->subtract(Money::rupiah(2));
})->throws(InvalidArgumentException::class);
