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
