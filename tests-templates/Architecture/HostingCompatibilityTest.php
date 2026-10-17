<?php

// TEMPLAT: salin ke tests/Architecture/HostingCompatibilityTest.php
// Menjaga CLAUDE.md M21 dan docs/rules/hosting.md: kode yang sama harus jalan di shared hosting.

declare(strict_types=1);

arch('NFR-16 tanpa eksekusi perintah sistem')
    ->expect(['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec'])
    ->not->toBeUsed();

arch('NFR-16 tanpa Process, Redis, Horizon, dan broadcast di kode aplikasi')
    ->expect(['Src', 'App'])
    ->not->toUse([
        'Symfony\Component\Process',
        'Illuminate\Support\Facades\Process',
        'Illuminate\Support\Facades\Redis',
        'Predis',
        'Laravel\Horizon',
        'Laravel\Reverb',
        'Illuminate\Broadcasting',
        'Illuminate\Contracts\Broadcasting',
    ]);

arch('NFR-16 PDF hanya lewat dompdf, bukan Chromium')
    ->expect(['Src', 'App'])
    ->not->toUse(['Spatie\LaravelPdf', 'Spatie\Browsershot']);

it('NFR-16 bawaan driver aman untuk shared hosting', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)->toMatch('/^SESSION_DRIVER=database$/m')
        ->and($env)->toMatch('/^QUEUE_CONNECTION=database$/m')
        ->and($env)->toMatch('/^CACHE_STORE=(database|file)$/m');
});

it('NFR-16 tidak ada route atau perintah web untuk migrasi dan Artisan', function () {
    foreach (app('router')->getRoutes() as $route) {
        expect($route->uri())->not->toMatch('#(artisan|migrate|phpinfo|install)#i');
    }
});
