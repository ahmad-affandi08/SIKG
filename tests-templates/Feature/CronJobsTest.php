<?php

// TEMPLAT: salin ke tests/Feature/CronJobsTest.php
// Antrean dan scheduler digerakkan cron di shared hosting (docs/rules/hosting.md butir 3-5).
// Jalankan dengan CACHE_STORE=database, SESSION_DRIVER=database, QUEUE_CONNECTION=database (scripts/verify.sh --shared).

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['queue.default' => 'database']);
});

it('SYS-07 schedule:run menulis denyut scheduler', function () {
    $this->artisan('schedule:run')->assertSuccessful();

    expect(DB::table('system_heartbeats')->where('name', 'scheduler')->exists())->toBeTrue();
});

it('NFR-16 queue:work --stop-when-empty menghabiskan antrean lalu berhenti', function () {
    dispatch(new \Src\Shared\Infrastructure\Jobs\ExampleJob('kunci-1')); // ganti dengan job nyata

    expect(DB::table('jobs')->count())->toBe(1);

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--max-time' => 10, '--tries' => 1])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('system_heartbeats')->where('name', 'queue')->exists())->toBeTrue();
});

it('NFR-16 job aman dijalankan dua kali (idempoten)', function () {
    dispatch(new \Src\Shared\Infrastructure\Jobs\ExampleJob('kunci-2'));
    dispatch(new \Src\Shared\Infrastructure\Jobs\ExampleJob('kunci-2'));

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--max-time' => 10])->assertSuccessful();

    // efek samping job hanya satu kali untuk kunci yang sama
    expect(DB::table('example_effects')->where('key', 'kunci-2')->count())->toBe(1);
});
