<?php

// TEMPLAT: salin ke tests/Feature/Scheduling/AppointmentConflictTest.php
// Butuh MySQL asli (bukan transaksi RefreshDatabase yang menutup koneksi lain): gunakan DatabaseMigrations
// dan proses terpisah (Concurrency::run dengan driver process) agar dua koneksi benar-benar bersaing.

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Concurrency;

uses(DatabaseMigrations::class);

it('JDW-02 dua resepsionis memesan slot dokter yang sama: hanya satu berhasil', function () {
    [$dokter, $kursi, $pasienA, $pasienB] = seedSchedulingFixture();   // helper uji, data sintetis
    $slot = '2000-01-03 09:00:00';                                     // tanggal sintetis tetap, bukan "hari ini"

    $hasil = Concurrency::run([
        fn () => bookViaHandler($pasienA, $dokter, $kursi, $slot, 30),
        fn () => bookViaHandler($pasienB, $dokter, $kursi, $slot, 30),
    ]);

    expect(collect($hasil)->where('ok', true))->toHaveCount(1)
        ->and(collect($hasil)->where('ok', false)->first()['code'])->toBe('appointment.conflict')
        ->and(appointmentCount($dokter, $slot))->toBe(1);
});

it('JDW-02 kursi yang sama tidak bisa dipakai dua dokter pada slot yang tumpang tindih', function () {
    [$dokter1, $dokter2, $kursi, $pasienA, $pasienB] = seedTwoDoctorsOneChair();

    $hasil = Concurrency::run([
        fn () => bookViaHandler($pasienA, $dokter1, $kursi, '2000-01-03 10:00:00', 30),
        fn () => bookViaHandler($pasienB, $dokter2, $kursi, '2000-01-03 10:15:00', 30),
    ]);

    expect(collect($hasil)->where('ok', true))->toHaveCount(1);
});

it('JDW-02 janji di luar jam praktik atau hari libur ditolak', function () {
    [$dokter, $kursi, $pasien] = seedSchedulingFixture(holiday: '2000-01-03');
    expect(bookViaHandler($pasien, $dokter, $kursi, '2000-01-03 09:00:00', 30)['code'])->toBe('appointment.outside_schedule');
});
