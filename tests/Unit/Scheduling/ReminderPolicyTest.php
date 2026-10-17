<?php

declare(strict_types=1);

use Src\Scheduling\Domain\ReminderPolicy;

function lokal(string $waktu): DateTimeImmutable
{
    return new DateTimeImmutable($waktu, new DateTimeZone('Asia/Jakarta'));
}

it('JDW-07 pengingat sehari sebelumnya mulai pukul 09.00 hari sebelum kunjungan', function () {
    $janji = lokal('2025-06-17 09:00');

    expect(ReminderPolicy::due($janji, lokal('2025-06-16 08:59')))->toBeNull()
        ->and(ReminderPolicy::due($janji, lokal('2025-06-16 09:00')))->toBe('h1')
        ->and(ReminderPolicy::due($janji, lokal('2025-06-16 23:30')))->toBe('h1');
});

it('JDW-07 pengingat hari kunjungan mulai pukul 07.00 dan hanya sebelum janji dimulai', function () {
    $janji = lokal('2025-06-17 10:00');

    expect(ReminderPolicy::due($janji, lokal('2025-06-17 06:59')))->toBeNull()
        ->and(ReminderPolicy::due($janji, lokal('2025-06-17 07:00')))->toBe('h0')
        ->and(ReminderPolicy::due($janji, lokal('2025-06-17 09:59')))->toBe('h0')
        ->and(ReminderPolicy::due($janji, lokal('2025-06-17 10:00')))->toBeNull();
});

it('JDW-07 janji lebih dari satu hari lagi atau sudah lewat tidak diingatkan', function () {
    expect(ReminderPolicy::due(lokal('2025-06-18 09:00'), lokal('2025-06-16 12:00')))->toBeNull()
        ->and(ReminderPolicy::due(lokal('2025-06-16 09:00'), lokal('2025-06-16 12:00')))->toBeNull();
});

it('JDW-07 pesan memuat klinik, waktu, dan dokter tanpa data klinis', function () {
    $besok = ReminderPolicy::message('h1', 'Klinik Uji', lokal('2025-06-17 09:00'), 'drg. Sari');
    $hariIni = ReminderPolicy::message('h0', 'Klinik Uji', lokal('2025-06-17 09:00'), 'drg. Sari');

    expect($besok)->toContain('Klinik Uji')->toContain('besok, 17/06/2025 pukul 09:00')->toContain('drg. Sari')
        ->and($hariIni)->toContain('hari ini pukul 09:00');
});
