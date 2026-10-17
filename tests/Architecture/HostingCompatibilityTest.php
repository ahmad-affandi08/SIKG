<?php

declare(strict_types=1);

// Menjaga CLAUDE.md M21 dan docs/rules/hosting.md: kode yang sama harus jalan di shared hosting.

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
