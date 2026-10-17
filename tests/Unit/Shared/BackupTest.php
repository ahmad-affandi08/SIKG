<?php

declare(strict_types=1);

use Src\Shared\Application\Backup\BackupFailed;
use Src\Shared\Domain\Backup\BackupRetention;
use Src\Shared\Infrastructure\Backup\BackupKey;
use Src\Shared\Infrastructure\Backup\BackupReader;
use Src\Shared\Infrastructure\Backup\BackupWriter;

function kunci(string $seed = 'a'): string
{
    return BackupKey::resolve(null, 'base64:'.base64_encode(str_repeat($seed, 32)));
}

/** @param list<array<string, mixed>> $records */
function tulisCadangan(array $records, string $key): string
{
    $stream = fopen('php://memory', 'w+');
    $writer = new BackupWriter($stream, $key);
    foreach ($records as $record) {
        $writer->write($record);
    }
    $writer->close();
    rewind($stream);

    return (string) stream_get_contents($stream);
}

/** @return list<array<string, mixed>> */
function bacaCadangan(string $bytes, string $key): array
{
    $stream = fopen('php://memory', 'w+');
    fwrite($stream, $bytes);
    rewind($stream);

    return iterator_to_array((new BackupReader($stream, $key))->records(), false);
}

it('SYS-02 retensi: semua 30 hari terakhir, tertua tiap bulan 12 bulan, tertua tiap tahun selamanya, terbaru tidak dibuang', function () {
    $now = new DateTimeImmutable('2026-10-17 04:00:00', new DateTimeZone('UTC'));
    $backups = [];
    foreach (['2026-10-16', '2026-10-01', '2026-09-20', '2026-09-02', '2026-08-15', '2025-11-20', '2025-10-10', '2025-10-05', '2024-06-01', '2024-06-20', '2023-02-02'] as $d) {
        $backups["sikg-{$d}.sikgbak"] = new DateTimeImmutable("{$d} 02:00:00", new DateTimeZone('UTC'));
    }

    $buang = BackupRetention::expired($backups, $now);

    expect($buang)->toContain('sikg-2025-10-10.sikgbak')   // bukan tertua bulannya dan sudah lewat 12 bulan
        ->and($buang)->toContain('sikg-2024-06-20.sikgbak') // bukan tertua bulannya dan bukan tertua tahunnya
        ->and($buang)->not->toContain('sikg-2026-10-16.sikgbak')
        ->and($buang)->not->toContain('sikg-2026-10-01.sikgbak')
        ->and($buang)->not->toContain('sikg-2026-09-20.sikgbak') // dalam 30 hari
        ->and($buang)->not->toContain('sikg-2026-09-02.sikgbak') // tertua September, 12 bulan
        ->and($buang)->not->toContain('sikg-2025-11-20.sikgbak') // tertua November 2025, dalam 12 bulan
        ->and($buang)->not->toContain('sikg-2025-10-05.sikgbak') // tertua tahun 2025
        ->and($buang)->not->toContain('sikg-2024-06-01.sikgbak') // tertua tahun 2024
        ->and($buang)->not->toContain('sikg-2023-02-02.sikgbak'); // tertua tahun 2023
});

it('SYS-02 retensi: cadangan tunggal dan daftar kosong tidak dibuang', function () {
    $now = new DateTimeImmutable('2030-01-01', new DateTimeZone('UTC'));
    expect(BackupRetention::expired([], $now))->toBe([])
        ->and(BackupRetention::expired(['sikg-x.sikgbak' => new DateTimeImmutable('2020-01-01', new DateTimeZone('UTC'))], $now))->toBe([]);
});

it('SYS-02 cadangan terenkripsi: bolak-balik utuh dan isi tidak terbaca sebagai teks biasa', function () {
    $records = [['t' => 'meta', 'version' => 1], ['t' => 'r', 'n' => 'patients', 'v' => ['Budi Santoso', 10, null, 1.5]]];
    $bytes = tulisCadangan($records, kunci());

    expect(bacaCadangan($bytes, kunci()))->toBe($records)
        ->and(str_contains($bytes, 'Budi Santoso'))->toBeFalse()
        ->and(str_starts_with($bytes, BackupWriter::MAGIC))->toBeTrue();
});

it('SYS-02 cadangan besar terbagi blok dan tetap utuh', function () {
    $records = [];
    for ($i = 0; $i < 4000; $i++) {
        $records[] = ['t' => 'r', 'n' => 'x', 'v' => [$i, str_repeat('data-', 40).$i]];
    }
    $bytes = tulisCadangan($records, kunci());

    expect(count(bacaCadangan($bytes, kunci())))->toBe(4000);
});

it('SYS-02 kunci salah, isi diubah, dan berkas terpotong ditolak dengan pesan jelas', function () {
    $bytes = tulisCadangan([['t' => 'meta'], ['t' => 'r', 'n' => 'x', 'v' => [1]]], kunci());

    $coba = function (string $data, string $key) {
        try {
            bacaCadangan($data, $key);

            return null;
        } catch (BackupFailed $e) {
            return $e->getMessage();
        }
    };

    expect($coba($bytes, kunci('b')))->toContain('kunci salah')
        ->and($coba(substr_replace($bytes, chr(ord($bytes[60]) ^ 1), 60, 1), kunci()))->toContain('diubah')
        ->and($coba(substr($bytes, 0, strlen($bytes) - 10), kunci()))->toContain('terpotong')
        ->and($coba('bukan cadangan sama sekali, hanya teks biasa', kunci()))->toContain('bukan cadangan');
});

it('SYS-02 kunci cadangan: BACKUP_KEY terpisah dari APP_KEY, keduanya wajib valid', function () {
    $app = 'base64:'.base64_encode(str_repeat('k', 32));

    expect(BackupKey::resolve(null, $app))->toBe(BackupKey::resolve(null, $app))
        ->and(strlen(BackupKey::resolve(null, $app)))->toBe(32)
        ->and(BackupKey::resolve('rahasia-cadangan-terpisah', $app))->not->toBe(BackupKey::resolve(null, $app));

    expect(fn () => BackupKey::resolve('pendek', $app))->toThrow(BackupFailed::class)
        ->and(fn () => BackupKey::resolve(null, null))->toThrow(BackupFailed::class);
});
