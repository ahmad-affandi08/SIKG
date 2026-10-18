<?php

declare(strict_types=1);

use Src\Clinical\Domain\Imaging\DicomReader;
use Src\Clinical\Domain\InvalidClinicalData;

it('P3-03 membaca DICOM explicit VR little endian dan memetakan piksel 16-bit ke abu 8-bit dengan rentang gambar', function () {
    $image = DicomReader::read(dicomSintetis());

    expect($image->renderable())->toBeTrue()->and($image->rows)->toBe(2)->and($image->columns)->toBe(4)->and($image->modality)->toBe('IO')->and($image->studyDate)->toBe('20250616')
        ->and($image->patientId)->toBeNull();
    $gray = array_values(unpack('C*', $image->toGray8()));
    // Nilai 0..1000 dipetakan ke 0..255 linear.
    expect($gray)->toHaveCount(8)->and($gray[0])->toBe(0)->and($gray[7])->toBe(255)->and($gray[1])->toBe(26)->and($gray[4])->toBe(102);
});

it('P3-03 windowing WindowCenter dan WindowWidth memotong nilai di luar jendela; MONOCHROME1 dibalik', function () {
    $window = DicomReader::read(dicomSintetis(['window' => [500, 400]]));
    $gray = array_values(unpack('C*', $window->toGray8()));
    // Jendela 300..700: 0,100,200,300 -> 0; 500 -> 128; 600 -> 191; 1000 -> 255.
    expect($gray[0])->toBe(0)->and($gray[3])->toBe(0)->and($gray[5])->toBe(128)->and($gray[6])->toBe(191)->and($gray[7])->toBe(255);

    $terbalik = DicomReader::read(dicomSintetis(['photometric' => 'MONOCHROME1']));
    $balik = array_values(unpack('C*', $terbalik->toGray8()));
    expect($balik[0])->toBe(255)->and($balik[7])->toBe(0);
});

it('P3-03 membaca implicit VR, explicit big endian, piksel 8-bit, dan piksel bertanda', function () {
    $implicit = DicomReader::read(dicomSintetis(['syntax' => '1.2.840.10008.1.2']));
    expect($implicit->renderable())->toBeTrue()->and($implicit->columns)->toBe(4)->and(array_values(unpack('C*', $implicit->toGray8()))[7])->toBe(255);

    $besar = DicomReader::read(dicomSintetis(['syntax' => '1.2.840.10008.1.2.2']));
    expect($besar->renderable())->toBeTrue()->and(array_values(unpack('C*', $besar->toGray8()))[7])->toBe(255)->and(array_values(unpack('C*', $besar->toGray8()))[0])->toBe(0);

    $delapan = DicomReader::read(dicomSintetis(['bits' => 8, 'pixels' => [10, 20, 30, 40, 50, 60, 70, 210]]));
    expect($delapan->renderable())->toBeTrue()->and(array_values(unpack('C*', $delapan->toGray8()))[7])->toBe(255)->and(array_values(unpack('C*', $delapan->toGray8()))[0])->toBe(0);

    $bertanda = DicomReader::read(dicomSintetis(['signed' => 1, 'pixels' => [-100, -50, 0, 50, 100, 150, 200, 300]]));
    expect(array_values(unpack('C*', $bertanda->toGray8()))[0])->toBe(0)->and(array_values(unpack('C*', $bertanda->toGray8()))[7])->toBe(255);
});

it('P3-03 melewati urutan berpanjang tak tentu sebelum data piksel dan membaca ID pasien', function () {
    foreach (['1.2.840.10008.1.2.1', '1.2.840.10008.1.2'] as $syntax) {
        $image = DicomReader::read(dicomSintetis(['syntax' => $syntax, 'sequence' => true, 'patient_id' => 'RM-0001']));

        expect($image->renderable())->toBeTrue()->and($image->patientId)->toBe('RM-0001')->and($image->rows)->toBe(2)->and($image->columns)->toBe(4);
    }
});

it('P3-03 melaporkan format terkompresi dan piksel yang tidak didukung dengan alasan jelas', function () {
    $jpeg = DicomReader::read(dicomSintetis(['syntax' => '1.2.840.10008.1.2.4.50']));
    expect($jpeg->renderable())->toBeFalse()->and($jpeg->unsupportedReason)->toContain('terkompresi')->and(fn () => $jpeg->toGray8())->toThrow(InvalidClinicalData::class);

    $warna = DicomReader::read(dicomSintetis(['photometric' => 'RGB']));
    expect($warna->renderable())->toBeFalse()->and($warna->unsupportedReason)->toContain('skala abu');

    $pendek = DicomReader::read(dicomSintetis(['rows' => 100, 'cols' => 100]));
    expect($pendek->renderable())->toBeFalse()->and($pendek->unsupportedReason)->toContain('lebih pendek');
});

it('P3-03 menolak berkas yang bukan DICOM atau rusak', function () {
    expect(fn () => DicomReader::read('bukan dicom'))->toThrow(InvalidClinicalData::class);
    expect(fn () => DicomReader::read(str_repeat("\0", 128).'XXXX'.str_repeat("\0", 20)))->toThrow(InvalidClinicalData::class);
    $terpotong = substr(dicomSintetis(['patient_id' => 'RM-0001']), 0, 190);
    $hasil = null;
    try {
        $hasil = DicomReader::read($terpotong);
    } catch (InvalidClinicalData) {
        $hasil = 'ditolak';
    }
    expect($hasil === 'ditolak' || ! $hasil->renderable())->toBeTrue();
});
