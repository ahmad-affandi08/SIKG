<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;
use Src\Shared\Application\HealthMonitor;

// Semua tugas terjadwal digerakkan satu cron `schedule:run` (docs/rules/hosting.md butir 5).
Schedule::call(fn () => app(HealthMonitor::class)->beat('scheduler'))
    ->name('sikg-heartbeat-scheduler')
    ->everyMinute();

Schedule::command('sikg:health-check')
    ->name('sikg-health-check')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

// SYS-02: cadangan hanya berjalan bila sudah jatuh tempo menurut interval, sehingga tahan cron yang terlewat.
Schedule::command('sikg:backup')
    ->name('sikg-backup')
    ->everyThirtyMinutes()
    ->withoutOverlapping(120);

Schedule::command('sikg:backup:verify')
    ->name('sikg-backup-verify')
    ->weeklyOn(0, '03:30')
    ->withoutOverlapping(120);

Schedule::command('sikg:backup:prune')
    ->name('sikg-backup-prune')
    ->dailyAt('04:30')
    ->withoutOverlapping(30);

// OBT-06: peringatan stok minimum dan kedaluwarsa terbit sekali per hari (setelah jam 07.00 lokal); aman diulang tiap 30 menit.
Schedule::command('sikg:stock:alerts')
    ->name('sikg-stock-alerts')
    ->everyThirtyMinutes()
    ->withoutOverlapping(20);

// P2-05: opname stok terjadwal; jadwal maju sekali per jatuh tempo, aman diulang.
Schedule::command('sikg:stock:count')
    ->name('sikg-stock-count')
    ->everyThirtyMinutes()
    ->withoutOverlapping(20);

// JDW-07: ringkasan janji hari ini (mulai 07.00) dan besok (mulai 09.00) untuk pemilik dan staf; sekali per hari, aman diulang.
Schedule::command('sikg:appointments:digest')
    ->name('sikg-appointment-digest')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10);

// P2-01: antrean kunjungan ke SATUSEHAT; percobaan ulang mengikuti jeda bertahap yang disimpan per pengiriman.
Schedule::command('sikg:satusehat:send')
    ->name('sikg-satusehat-send')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

// P2-03: pengingat pasien (H-1, hari-H, kontrol berkala); idempoten per janji atau kunjungan, tahan cron yang terlewat.
Schedule::command('sikg:reminders:run')
    ->name('sikg-reminders-run')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10);

// SYS-05: pemberitahuan dalam aplikasi dipangkas setelah 90 hari.
Schedule::command('sikg:notifications:prune')
    ->name('sikg-notifications-prune')
    ->dailyAt('05:15')
    ->withoutOverlapping(30);

// LAP: berkas ekspor laporan dilepas setelah masa simpan; tahan cron yang terlewat karena hanya menghapus yang kedaluwarsa.
Schedule::command('sikg:reports:prune')
    ->name('sikg-reports-prune')
    ->dailyAt('05:00')
    ->withoutOverlapping(30);
