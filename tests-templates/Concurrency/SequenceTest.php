<?php

// TEMPLAT: salin ke tests/Feature/SequenceTest.php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Concurrency;

uses(DatabaseMigrations::class);

it('PAS-01 nomor RM unik dan berurutan saat pendaftaran serentak', function () {
    $tugas = array_map(fn (int $i) => fn () => registerPatientViaHandler("Pasien Uji {$i}", sprintf('99990000%08d', $i)), range(1, 8));

    $hasil = Concurrency::run($tugas);

    $nomor = collect($hasil)->pluck('medical_record_no');
    expect($nomor->unique())->toHaveCount(8)
        ->and($nomor->sort()->values()->all())->toBe($nomor->sort()->values()->all());
});

it('KSR-06 nomor invoice unik saat dua kasir menerbitkan serentak', function () {
    $hasil = Concurrency::run([
        fn () => issueInvoiceViaHandler(createEncounterFixture()),
        fn () => issueInvoiceViaHandler(createEncounterFixture()),
    ]);
    expect(collect($hasil)->pluck('number')->unique())->toHaveCount(2);
});
