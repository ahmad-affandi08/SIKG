<?php

declare(strict_types=1);

use Src\Clinical\Application\ClinicalDocuments;
use Src\Clinical\Domain\DocumentDefaults;
use Src\Clinical\Domain\DocumentType;

it('RM-09 isi dokumen dipecah menjadi paragraf, daftar label-isi, dan daftar bernomor', function () {
    $blocks = ClinicalDocuments::blocks("Pembuka surat.\n\nDiagnosis: Karies dentin\nTindakan: Pencabutan gigi 46\n\n1. Pertama\n2. Kedua\n\nKepada Yth.\ndrg. Andi\ndi tempat");

    expect($blocks[0])->toBe(['kind' => 'text', 'text' => 'Pembuka surat.'])
        ->and($blocks[1])->toBe(['kind' => 'kv', 'rows' => [['Diagnosis', 'Karies dentin'], ['Tindakan', 'Pencabutan gigi 46']]])
        ->and($blocks[2])->toBe(['kind' => 'list', 'items' => ['Pertama', 'Kedua']])
        ->and($blocks[3])->toBe(['kind' => 'text', 'text' => "Kepada Yth.\ndrg. Andi\ndi tempat"]);
});

it('RM-09 satu baris bertitik dua tetap paragraf dan baris campuran tidak dipaksa menjadi tabel', function () {
    $blocks = ClinicalDocuments::blocks("Catatan: hanya satu baris\n\nNama: Budi\nkalimat tanpa label");

    expect($blocks[0]['kind'])->toBe('text')->and($blocks[1]['kind'])->toBe('text');
});

it('RM-09 templat bawaan tiap jenis menghasilkan tabel informasi yang terstruktur', function () {
    $consent = ClinicalDocuments::blocks(DocumentDefaults::body(DocumentType::Consent));
    $kv = array_values(array_filter($consent, fn (array $b) => $b['kind'] === 'kv'));
    $labels = array_map(fn (array $row) => $row[0], $kv[0]['rows']);

    expect($labels)->toBe(['Diagnosis', 'Tindakan', 'Tujuan tindakan', 'Tata cara', 'Risiko dan komplikasi', 'Alternatif dan risikonya', 'Prognosis', 'Perkiraan biaya']);

    $rujukan = ClinicalDocuments::blocks(DocumentDefaults::body(DocumentType::Referral));
    $kvRujukan = array_values(array_filter($rujukan, fn (array $b) => $b['kind'] === 'kv'));
    expect(array_map(fn (array $r) => $r[0], $kvRujukan[1]['rows']))->toBe(['Keluhan dan riwayat', 'Diagnosa sementara', 'Terapi atau tindakan yang telah diberikan', 'Alasan rujukan']);
});

it('RM-10 hanya surat rujukan memakai blok Nomor, Lampiran, Perihal', function () {
    expect(DocumentType::Referral->isOfficialLetter())->toBeTrue()
        ->and(DocumentType::Certificate->isOfficialLetter())->toBeFalse()
        ->and(DocumentType::Consent->isOfficialLetter())->toBeFalse()
        ->and(DocumentType::Referral->subject())->toBe('Rujukan pasien');
});
