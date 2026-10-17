<?php

declare(strict_types=1);

/*
 * ADR-0008: semua cetakan PDF memakai satu layout, satu kop, dan hitam putih. Tes ini mencegah templat baru
 * membawa gaya kop sendiri atau warna teks.
 */

/** @return list<string> */
function templatCetak(): array
{
    $dir = dirname(__DIR__, 2).'/resources/views/pdf';
    $files = [];
    foreach (glob($dir.'/*.blade.php') ?: [] as $file) {
        if (basename($file) !== 'layout.blade.php') {
            $files[] = $file;
        }
    }

    return $files;
}

it('ADR-0008 setiap templat PDF memperluas layout bersama dan tidak mendefinisikan kop sendiri', function () {
    expect(templatCetak())->not->toBeEmpty();
    foreach (templatCetak() as $file) {
        $isi = (string) file_get_contents($file);
        expect($isi)->toContain("@extends('pdf.layout')")
            ->and($isi)->not->toContain('<html')
            ->and($isi)->not->toContain('.kop {')
            ->and($isi)->not->toContain('class="kop"');
    }
});

it('ADR-0008 gaya cetak hitam putih: semua warna heksadesimal adalah abu-abu', function () {
    $berkas = [...templatCetak(), ...(glob(dirname(__DIR__, 2).'/resources/views/pdf/partials/*.blade.php') ?: []), dirname(__DIR__, 2).'/resources/views/pdf/layout.blade.php'];
    foreach ($berkas as $file) {
        preg_match_all('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', (string) file_get_contents($file), $found);
        foreach ($found[1] as $hex) {
            $hex = strlen($hex) === 3 ? $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2] : $hex;
            expect(strtolower(substr($hex, 0, 2)) === strtolower(substr($hex, 2, 2)) && strtolower(substr($hex, 2, 2)) === strtolower(substr($hex, 4, 2)))->toBeTrue();
        }
    }
});

it('ADR-0008 kop memakai garis ganda dan logo di kiri, nama di tengah', function () {
    $kop = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/pdf/partials/letterhead.blade.php');
    $gaya = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/pdf/partials/style.blade.php');

    expect($kop)->toContain('class="logo"')->and($kop)->toContain('class="body"')
        ->and($gaya)->toContain('3pt double')->and($gaya)->toContain('text-align: center');
});
