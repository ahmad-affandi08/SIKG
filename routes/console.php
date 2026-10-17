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
