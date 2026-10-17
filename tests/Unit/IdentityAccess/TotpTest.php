<?php

declare(strict_types=1);

use Src\IdentityAccess\Domain\TwoFactor\Base32;
use Src\IdentityAccess\Domain\TwoFactor\RecoveryCodes;
use Src\IdentityAccess\Domain\TwoFactor\Totp;

// Rahasia uji RFC 6238 (ASCII "12345678901234567890"); kode 8 digit di RFC dipotong ke 6 digit terakhir.
const RAHASIA_RFC = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

it('AUTH-05 Base32 sesuai RFC 4648 dan dapat dibalik', function () {
    expect(Base32::encode('12345678901234567890'))->toBe(RAHASIA_RFC)
        ->and(Base32::decode(RAHASIA_RFC))->toBe('12345678901234567890')
        ->and(Base32::decode('gezd gnbv-gy3t'))->toBe(Base32::decode('GEZDGNBVGY3T'))
        ->and(Base32::decode('1!'))->toBeNull();
});

it('AUTH-05 TOTP cocok dengan vektor uji RFC 6238', function (int $waktu, string $kode) {
    expect(Totp::code(RAHASIA_RFC, Totp::step($waktu)))->toBe($kode);
})->with([
    'T=59' => [59, '287082'],
    'T=1111111109' => [1111111109, '081804'],
    'T=1234567890' => [1234567890, '005924'],
    'T=2000000000' => [2000000000, '279037'],
]);

it('AUTH-05 kode diterima dalam toleransi satu langkah dan langkah yang sudah dipakai ditolak', function () {
    $waktu = 1234567890;
    $kode = Totp::code(RAHASIA_RFC, Totp::step($waktu));

    expect(Totp::verify(RAHASIA_RFC, $kode, $waktu))->toBe(Totp::step($waktu))
        ->and(Totp::verify(RAHASIA_RFC, $kode, $waktu + Totp::PERIOD))->toBe(Totp::step($waktu))
        ->and(Totp::verify(RAHASIA_RFC, $kode, $waktu + 3 * Totp::PERIOD))->toBeNull()
        ->and(Totp::verify(RAHASIA_RFC, $kode, $waktu, Totp::step($waktu)))->toBeNull();
});

it('AUTH-05 kode yang bukan 6 digit ditolak tanpa menghitung', function () {
    expect(Totp::verify(RAHASIA_RFC, '12345', 1234567890))->toBeNull()
        ->and(Totp::verify(RAHASIA_RFC, 'abcdef', 1234567890))->toBeNull()
        ->and(Totp::verify(RAHASIA_RFC, '', 1234567890))->toBeNull();
});

it('AUTH-05 rahasia baru berupa Base32 dua puluh byte dan alamat otpauth memuat penerbit', function () {
    $rahasia = Totp::newSecret();
    $uri = Totp::uri($rahasia, 'pemilik@contoh.test', 'SIKG');

    expect(strlen(Base32::decode($rahasia) ?? ''))->toBe(20)->and($rahasia)->not->toBe(Totp::newSecret())
        ->and($uri)->toStartWith('otpauth://totp/SIKG%3Apemilik%40contoh.test?')->toContain('secret='.$rahasia)->toContain('issuer=SIKG')->toContain('digits=6')->toContain('period=30');
});

it('AUTH-05 kode pemulihan berbentuk baku, unik, dan dinormalkan saat dicocokkan', function () {
    $kode = RecoveryCodes::generate();

    expect($kode)->toHaveCount(10)->and(array_unique($kode))->toHaveCount(10)
        ->and($kode[0])->toMatch('/^[a-z2-9]{5}-[a-z2-9]{5}$/')
        ->and(RecoveryCodes::normalize(strtoupper(" {$kode[0]} ")))->toBe($kode[0])
        ->and(RecoveryCodes::normalize(str_replace('-', '', $kode[0])))->toBe($kode[0])
        ->and(RecoveryCodes::normalize('123456'))->toBeNull()
        ->and(RecoveryCodes::hash($kode[0], 'k'))->not->toBe(RecoveryCodes::hash($kode[1], 'k'))->and(RecoveryCodes::hash($kode[0], 'k'))->not->toBe(RecoveryCodes::hash($kode[0], 'x'));
});
