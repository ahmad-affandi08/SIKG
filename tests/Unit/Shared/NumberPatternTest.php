<?php

declare(strict_types=1);

use Src\Shared\Domain\NumberPattern;

it('SYS-04 nomor RM dirender dari format yang dapat diatur', function () {
    $at = new DateTimeImmutable('2030-03-05 10:00:00', new DateTimeZone('UTC'));

    expect((new NumberPattern('RM-{seq:6}'))->render(1, $at))->toBe('RM-000001')
        ->and((new NumberPattern('INV-{yyyy}-{seq:5}'))->render(42, $at))->toBe('INV-2030-00042')
        ->and((new NumberPattern('{yy}{mm}/{seq}'))->render(7, $at))->toBe('3003/7')
        ->and((new NumberPattern('K{dd}.{seq:3}'))->render(12, $at))->toBe('K05.012');
});

it('SYS-04 tanggal dirender di zona waktu klinik, bukan UTC', function () {
    // 20:00 UTC pada akhir tahun sudah lewat tengah malam di WIB (tahun berikutnya)
    $at = new DateTimeImmutable('2030-12-31 20:00:00', new DateTimeZone('UTC'));
    $pattern = new NumberPattern('INV-{yyyy}-{seq:3}');

    expect($pattern->render(1, $at, 'UTC'))->toBe('INV-2030-001')
        ->and($pattern->render(1, $at, 'Asia/Jakarta'))->toBe('INV-2031-001');
});

it('ADR-0004 lingkup nomor urut mengikuti token tanggal', function () {
    $at = new DateTimeImmutable('2030-03-05 10:00:00', new DateTimeZone('UTC'));

    expect((new NumberPattern('RM-{seq:6}'))->scopeKey($at))->toBe('')
        ->and((new NumberPattern('INV-{yyyy}-{seq:5}'))->scopeKey($at))->toBe('2030')
        ->and((new NumberPattern('{yy}{mm}-{seq}'))->scopeKey($at))->toBe('2030-03')
        ->and((new NumberPattern('{yyyy}{mm}{dd}-{seq}'))->scopeKey($at))->toBe('2030-03-05');
});

it('SYS-04 format tidak sah ditolak', function (string $pattern) {
    new NumberPattern($pattern);
})->with([
    'tanpa seq' => ['RM-001'],
    'dua seq' => ['{seq}-{seq}'],
    'karakter berbahaya' => ['RM <{seq}>'],
    'spasi' => ['RM {seq}'],
    'terlalu panjang' => [str_repeat('A', 40).'{seq}'],
    'token tak dikenal' => ['RM-{xyz}-{seq}'],
])->throws(InvalidArgumentException::class);

it('SYS-04 nomor urut harus mulai dari 1', function () {
    (new NumberPattern('{seq}'))->render(0, new DateTimeImmutable);
})->throws(InvalidArgumentException::class);
