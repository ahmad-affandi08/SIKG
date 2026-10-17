<?php

declare(strict_types=1);

use Src\Clinic\Domain\ClinicSettings;

it('SYS-04 pengaturan bawaan: sesi 30 menit, Senin-Sabtu 08:00-17:00, Minggu tutup', function () {
    $settings = ClinicSettings::defaults();

    expect($settings->sessionTimeoutMinutes)->toBe(30)
        ->and($settings->medicalRecordPattern->pattern)->toBe('RM-{seq:6}')
        ->and($settings->isOpenOn(1))->toBeTrue()
        ->and($settings->isOpenOn(6))->toBeTrue()
        ->and($settings->isOpenOn(7))->toBeFalse()
        ->and($settings->operatingHours[1])->toBe(['open' => '08:00', 'close' => '17:00']);
});

it('SYS-04 pengaturan dapat disimpan dan dibaca kembali tanpa kehilangan nilai', function () {
    $settings = ClinicSettings::fromArray([
        'session_timeout_minutes' => 45,
        'invoice_pattern' => 'FKT/{yy}/{seq:4}',
        'operating_hours' => [7 => ['open' => '09:00', 'close' => '12:00'], 3 => null],
    ]);

    $again = ClinicSettings::fromArray($settings->toArray());

    expect($again->sessionTimeoutMinutes)->toBe(45)
        ->and($again->invoicePattern->pattern)->toBe('FKT/{yy}/{seq:4}')
        ->and($again->isOpenOn(7))->toBeTrue()
        ->and($again->isOpenOn(3))->toBeFalse()
        ->and($again->medicalRecordPattern->pattern)->toBe('RM-{seq:6}');
});

it('SYS-04 data tersimpan kosong memakai bawaan', function () {
    expect(ClinicSettings::fromArray(null)->sessionTimeoutMinutes)->toBe(30);
});

it('AUTH-04 batas sesi di bawah minimum ditolak', function () {
    ClinicSettings::fromArray(['session_timeout_minutes' => 4]);
})->throws(InvalidArgumentException::class);

it('SYS-04 jam operasional tidak sah ditolak', function (array $hours) {
    ClinicSettings::fromArray(['operating_hours' => [1 => $hours]]);
})->with([
    'tutup sebelum buka' => [['open' => '17:00', 'close' => '08:00']],
    'format salah' => [['open' => '8', 'close' => '17:00']],
    'jam di luar rentang' => [['open' => '08:00', 'close' => '25:00']],
])->throws(InvalidArgumentException::class);
