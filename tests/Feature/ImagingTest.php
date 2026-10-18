<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Src\IdentityAccess\Domain\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
    Storage::fake('private');
});

afterEach(function () {
    Carbon::setTestNow();
});

function nomorRm(array $s): string
{
    return (string) DB::table('patients')->where('id', $s['pasien'])->value('medical_record_no');
}

/** @param array<string, mixed> $o */
function unggahDicom(array $s, array $o = [], ?string $isi = null)
{
    $isi ??= dicomSintetis();

    return test()->actingAs($s['dokter_user'])->post('/imaging/dicom', [
        'encounter_id' => $s['kunjungan'], 'file' => UploadedFile::fake()->createWithContent('rontgen.dcm', $isi), ...$o,
    ]);
}

function folderMasuk(array $s, string $nama, string $isi): void
{
    Storage::disk('private')->put("imaging-inbox/{$s['klinik']->id}/{$nama}", $isi);
}

it('P3-03 DICOM diunggah ke kunjungan: pratinjau PNG dan berkas asli disimpan sebagai rontgen', function () {
    $s = siapKunjungan();

    unggahDicom($s, ['tooth_fdi' => 46])->assertSessionHasNoErrors()->assertRedirect("/encounters/{$s['kunjungan']}?tab=files");

    $berkas = DB::table('clinical_files')->orderBy('created_at')->orderBy('id')->get();
    expect($berkas)->toHaveCount(2);
    $png = $berkas->firstWhere('mime', 'image/png');
    $asli = $berkas->firstWhere('mime', 'application/dicom');
    expect($png->kind)->toBe('xray')->and((int) $png->tooth_fdi)->toBe(46)->and($png->caption)->toBe('Rontgen digital IO 16/06/2025 (4x2)')->and($asli->kind)->toBe('xray')
        ->and($asli->caption)->toStartWith('Berkas DICOM asli')->and($png->encounter_id)->toBe($s['kunjungan'])->and($png->patient_id)->toBe($s['pasien']);
    expect(DB::table('audit_logs')->where('action', 'encounter.dicom_imported')->count())->toBe(1);

    $tampil = $this->get("/clinical-files/{$png->id}")->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(substr((string) $tampil->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1A\n");
    [$lebar, $tinggi] = getimagesizefromstring((string) $tampil->getContent());
    expect([$lebar, $tinggi])->toBe([4, 2]);

    $unduh = $this->get("/clinical-files/{$asli->id}")->assertOk();
    expect((string) $unduh->headers->get('Content-Disposition'))->toContain('attachment')->and(substr((string) $unduh->getContent(), 128, 4))->toBe('DICM');
    expect(DB::table('access_logs')->where('route', 'clinical-files.show')->count())->toBe(2);
});

it('P3-03 ID pasien di dalam DICOM dicocokkan dengan nomor RM; selisih ditolak kecuali ditegaskan', function () {
    $s = siapKunjungan();

    unggahDicom($s, [], dicomSintetis(['patient_id' => 'RM-LAIN-999']))->assertSessionHas('error_code', 'clinical.dicom_patient_mismatch');
    expect(DB::table('clinical_files')->count())->toBe(0);

    unggahDicom($s, ['confirm_mismatch' => true], dicomSintetis(['patient_id' => 'RM-LAIN-999']))->assertSessionHasNoErrors();
    expect(DB::table('clinical_files')->count())->toBe(2)->and(json_decode((string) DB::table('audit_logs')->where('action', 'encounter.dicom_imported')->value('after'), true)['mismatch_confirmed'] ?? null)->toBeTrue();

    DB::table('clinical_files')->delete();
    unggahDicom($s, [], dicomSintetis(['patient_id' => nomorRm($s)]))->assertSessionHasNoErrors();
    expect(DB::table('clinical_files')->count())->toBe(2);
});

it('P3-03 impor ditolak untuk berkas bukan DICOM, format terkompresi, kunjungan tertutup, dan pengguna tanpa izin', function () {
    $s = siapKunjungan();

    unggahDicom($s, [], 'ini bukan dicom')->assertSessionHas('error_code', 'clinical.dicom_invalid');
    unggahDicom($s, [], dicomSintetis(['syntax' => '1.2.840.10008.1.2.4.50']))->assertSessionHas('error_code', 'clinical.dicom_unsupported');
    unggahDicom($s, ['tooth_fdi' => 99])->assertSessionHas('error');
    unggahDicom($s, ['encounter_id' => ''])->assertSessionHasErrors('encounter_id');
    expect(DB::table('clinical_files')->count())->toBe(0);

    $kasir = makeUser(Role::Kasir, $s['klinik']);
    $this->actingAs($kasir)->post('/imaging/dicom', ['encounter_id' => $s['kunjungan'], 'file' => UploadedFile::fake()->createWithContent('a.dcm', dicomSintetis())])->assertForbidden();
    $this->actingAs($kasir)->get('/imaging/inbox')->assertForbidden();
    auth()->logout();
    $this->get('/imaging/inbox')->assertRedirect('/login');

    DB::table('encounters')->where('id', $s['kunjungan'])->update(['status' => 'closed', 'closed_at' => now()]);
    unggahDicom($s)->assertSessionHas('error_code', 'encounter.locked');
    expect(DB::table('clinical_files')->count())->toBe(0);
});

it('P3-03 folder masuk menampilkan gambar yang menunggu dan kunjungan tujuan', function () {
    $s = siapKunjungan();
    folderMasuk($s, 'sensor-001.dcm', dicomSintetis());
    folderMasuk($s, 'foto.png', "\x89PNG\r\n\x1A\nxxxx");
    folderMasuk($s, 'catatan.txt', 'abaikan');

    $this->actingAs($s['dokter_user'])->get('/imaging/inbox')->assertOk()->assertInertia(fn ($p) => $p->component('Imaging/Inbox')
        ->has('files', 2)->where('encounters.0.value', $s['kunjungan'])->where('encounters.0.label', fn ($v) => str_contains((string) $v, 'Budi Santoso') && str_contains((string) $v, nomorRm($s))));
});

it('P3-03 berkas dari folder masuk dilampirkan lalu diarsipkan; PNG dilampirkan apa adanya', function () {
    $s = siapKunjungan();
    folderMasuk($s, 'sensor-001.dcm', dicomSintetis());
    $png = imagecreatetruecolor(3, 3);
    ob_start();
    imagepng($png);
    $gambar = (string) ob_get_clean();
    folderMasuk($s, 'foto.png', $gambar);

    $this->actingAs($s['dokter_user'])->post('/imaging/inbox/import', ['name' => 'sensor-001.dcm', 'encounter_id' => $s['kunjungan'], 'tooth_fdi' => 16])->assertSessionHasNoErrors();
    $this->post('/imaging/inbox/import', ['name' => 'foto.png', 'encounter_id' => $s['kunjungan']])->assertSessionHasNoErrors();

    expect(DB::table('clinical_files')->count())->toBe(3)->and(DB::table('clinical_files')->where('mime', 'application/dicom')->count())->toBe(1)
        ->and(DB::table('clinical_files')->where('mime', 'image/png')->where('kind', 'xray')->count())->toBe(2);
    $dir = "imaging-inbox/{$s['klinik']->id}";
    expect(Storage::disk('private')->files($dir))->toBe([])
        ->and(Storage::disk('private')->files("{$dir}/imported"))->toHaveCount(2);
    expect(DB::table('audit_logs')->where('action', 'imaging.inbox_imported')->count())->toBe(2);

    $this->post('/imaging/inbox/import', ['name' => 'sensor-001.dcm', 'encounter_id' => $s['kunjungan']])->assertSessionHas('error_code', 'clinical.inbox_missing');
});

it('P3-03 gagal impor tidak mengarsipkan berkas, dan berkas dapat dibuang ke arsip ditolak', function () {
    $s = siapKunjungan();
    folderMasuk($s, 'rusak.dcm', 'bukan dicom');
    folderMasuk($s, 'salah-pasien.dcm', dicomSintetis(['patient_id' => 'RM-LAIN-999']));
    folderMasuk($s, 'buang.dcm', dicomSintetis());
    $dir = "imaging-inbox/{$s['klinik']->id}";

    $this->actingAs($s['dokter_user'])->post('/imaging/inbox/import', ['name' => 'rusak.dcm', 'encounter_id' => $s['kunjungan']])->assertSessionHas('error_code', 'clinical.dicom_invalid');
    $this->post('/imaging/inbox/import', ['name' => 'salah-pasien.dcm', 'encounter_id' => $s['kunjungan']])->assertSessionHas('error_code', 'clinical.dicom_patient_mismatch');
    expect(Storage::disk('private')->files($dir))->toHaveCount(3)->and(DB::table('clinical_files')->count())->toBe(0);

    $this->post('/imaging/inbox/import', ['name' => 'salah-pasien.dcm', 'encounter_id' => $s['kunjungan'], 'confirm_mismatch' => true])->assertSessionHasNoErrors();
    $this->post('/imaging/inbox/discard', ['name' => 'buang.dcm'])->assertSessionHasNoErrors();
    expect(Storage::disk('private')->files("{$dir}/rejected"))->toHaveCount(1)->and(Storage::disk('private')->files($dir))->toHaveCount(1);
});

it('P3-03 nama berkas yang mencoba keluar dari folder masuk ditolak', function () {
    $s = siapKunjungan();
    Storage::disk('private')->put('rahasia.dcm', dicomSintetis());

    foreach (['../rahasia.dcm', '..%2Frahasia.dcm', 'a/b.dcm', '/etc/passwd', 'x.exe', ''] as $nama) {
        $this->actingAs($s['dokter_user'])->post('/imaging/inbox/import', ['name' => $nama, 'encounter_id' => $s['kunjungan']]);
        expect(session()->has('error_code') || session()->has('errors'))->toBeTrue();
    }
    $this->post('/imaging/inbox/discard', ['name' => '../rahasia.dcm'])->assertSessionHas('error_code', 'clinical.inbox_name');
    expect(Storage::disk('private')->exists('rahasia.dcm'))->toBeTrue()->and(DB::table('clinical_files')->count())->toBe(0);
});

it('P3-03 pemindaian terjadwal memberi tahu petugas sekali per jumlah berkas per hari', function () {
    $s = siapKunjungan();

    Artisan::call('sikg:imaging:scan');
    expect(DB::table('notifications')->where('kind', 'imaging.inbox')->count())->toBe(0);

    folderMasuk($s, 'a.dcm', dicomSintetis());
    Artisan::call('sikg:imaging:scan');
    Artisan::call('sikg:imaging:scan');
    expect(DB::table('notifications')->where('kind', 'imaging.inbox')->count())->toBe(1);

    folderMasuk($s, 'b.dcm', dicomSintetis());
    Artisan::call('sikg:imaging:scan');
    $notif = DB::table('notifications')->where('kind', 'imaging.inbox')->orderByDesc('created_at')->first();
    expect(DB::table('notifications')->where('kind', 'imaging.inbox')->count())->toBe(2)->and($notif->link)->toBe('/imaging/inbox')->and(json_encode($notif))->not->toContain('Budi');
});

it('P3-03 klinik lain tidak melihat folder masuk klinik ini', function () {
    $s = siapKunjungan();
    folderMasuk($s, 'a.dcm', dicomSintetis());
    $lain = siapKunjungan();

    $this->actingAs($lain['dokter_user'])->get('/imaging/inbox')->assertInertia(fn ($p) => $p->has('files', 0));
    $this->post('/imaging/inbox/import', ['name' => 'a.dcm', 'encounter_id' => $lain['kunjungan']])->assertSessionHas('error_code', 'clinical.inbox_missing');
});
