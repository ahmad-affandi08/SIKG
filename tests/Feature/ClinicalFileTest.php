<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Src\Clinical\Infrastructure\Eloquent\ClinicalFileModel;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
    Storage::fake('private');
});

afterEach(function () {
    Carbon::setTestNow();
});

function unggahBerkas(array $s, UploadedFile $file, array $o = [])
{
    return test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/files", ['file' => $file, 'kind' => 'xray', ...$o]);
}

function pdfUji(string $nama = 'lampiran.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nama, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
}

it('RM-08 mengunggah rontgen PNG, foto JPG, dan dokumen PDF ke kunjungan', function () {
    $s = siapKunjungan();

    unggahBerkas($s, UploadedFile::fake()->image('r.png', 50, 50), ['kind' => 'xray', 'tooth_fdi' => 46, 'caption' => 'Periapikal 46'])->assertSessionHasNoErrors();
    unggahBerkas($s, UploadedFile::fake()->image('f.jpg', 50, 50), ['kind' => 'photo'])->assertSessionHasNoErrors();
    unggahBerkas($s, pdfUji(), ['kind' => 'document'])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $berkas = ClinicalFileModel::query()->orderBy('kind')->get()->keyBy('kind');
    expect($berkas)->toHaveCount(3)
        ->and($berkas['xray']->mime)->toBe('image/png')->and($berkas['xray']->tooth_fdi)->toBe(46)->and($berkas['xray']->caption)->toBe('Periapikal 46')
        ->and($berkas['photo']->mime)->toBe('image/jpeg')->and($berkas['document']->mime)->toBe('application/pdf')
        ->and($berkas['xray']->path)->toStartWith("clinical/{$s['klinik']->id}/{$s['pasien']}/")->and($berkas['xray']->path)->toEndWith('.png');
});

it('RM-08 berkas tidak memakai nama asli dan tersimpan terenkripsi di disk privat', function () {
    config(['sikg.encrypt_files' => true]);
    $s = siapKunjungan();

    unggahBerkas($s, UploadedFile::fake()->image('Budi Santoso rontgen.png', 50, 50));

    tenant()->set($s['klinik']->id);
    $f = ClinicalFileModel::query()->firstOrFail();
    expect($f->path)->not->toContain('Budi')->and($f->encrypted)->toBeTrue();
    Storage::disk('private')->assertExists($f->path);
    expect(str_starts_with(Storage::disk('private')->get($f->path), "\x89PNG"))->toBeFalse();
});

it('RM-08 berkas dapat disajikan lewat controller dengan isi aslinya dan tercatat di access log', function () {
    config(['sikg.encrypt_files' => true]);
    $s = siapKunjungan();
    $png = UploadedFile::fake()->image('r.png', 30, 30);
    $asli = file_get_contents($png->getPathname());
    unggahBerkas($s, $png);
    tenant()->set($s['klinik']->id);
    $id = ClinicalFileModel::query()->firstOrFail()->id;

    $respons = test()->actingAs($s['perawat'])->get("/clinical-files/{$id}")->assertOk()->assertHeader('Content-Type', 'image/png');

    expect($respons->getContent())->toBe($asli);
    expect(DB::table('access_logs')->where('patient_id', $s['pasien'])->where('actor_id', $s['perawat']->id)->exists())->toBeTrue();
});

it('RM-08 berkas tanpa enkripsi disajikan apa adanya saat enkripsi dimatikan', function () {
    config(['sikg.encrypt_files' => false]);
    $s = siapKunjungan();
    unggahBerkas($s, pdfUji(), ['kind' => 'document']);
    tenant()->set($s['klinik']->id);
    $id = ClinicalFileModel::query()->firstOrFail()->id;

    test()->actingAs($s['dokter_user'])->get("/clinical-files/{$id}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('RM-08 menolak SVG, skrip, berkas bukan gambar, dan ekstensi yang menyamar', function () {
    $s = siapKunjungan();

    unggahBerkas($s, UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))->assertSessionHasErrors('file');
    unggahBerkas($s, UploadedFile::fake()->createWithContent('x.php', '<?php echo 1;'))->assertSessionHasErrors('file');
    $menyamar = tempnam(sys_get_temp_dir(), 'ujiberkas');
    file_put_contents($menyamar, '<?php echo "bukan gambar";');
    unggahBerkas($s, new UploadedFile($menyamar, 'rontgen.png', 'image/png', null, true))->assertSessionHasErrors('file');
    unggahBerkas($s, UploadedFile::fake()->createWithContent('x.html', '<html><script>alert(1)</script></html>'))->assertSessionHasErrors('file');

    tenant()->set($s['klinik']->id);
    expect(ClinicalFileModel::query()->count())->toBe(0);
});

it('RM-08 menolak ukuran lebih dari 10 MB dan jenis berkas atau gigi yang tidak sah', function () {
    $s = siapKunjungan();

    unggahBerkas($s, UploadedFile::fake()->image('besar.png')->size(10241))->assertSessionHasErrors('file');
    unggahBerkas($s, UploadedFile::fake()->image('r.png'), ['kind' => 'lain'])->assertSessionHasErrors('kind');
    unggahBerkas($s, UploadedFile::fake()->image('r.png'), ['tooth_fdi' => 19])->assertSessionHasErrors('domain');
    unggahBerkas($s, UploadedFile::fake()->image('ok.png'), [])->assertSessionHasNoErrors();
});

it('RM-08 setelah kunjungan ditutup, berkas tidak dapat ditambah atau dilepas', function () {
    $s = siapKunjungan();
    unggahBerkas($s, UploadedFile::fake()->image('r.png'));
    tenant()->set($s['klinik']->id);
    $id = ClinicalFileModel::query()->firstOrFail()->id;
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();

    unggahBerkas($s, UploadedFile::fake()->image('baru.png'))->assertSessionHasErrors('domain');
    test()->actingAs($s['dokter_user'])->delete("/encounters/{$s['kunjungan']}/files/{$id}")->assertSessionHasErrors('domain');
});

it('RM-08 melepas berkas dari kunjungan berjalan memakai soft delete dan berkas fisik tetap ada', function () {
    $s = siapKunjungan();
    unggahBerkas($s, UploadedFile::fake()->image('r.png'));
    tenant()->set($s['klinik']->id);
    $f = ClinicalFileModel::query()->firstOrFail();

    test()->actingAs($s['dokter_user'])->delete("/encounters/{$s['kunjungan']}/files/{$f->id}")->assertRedirect();

    tenant()->set($s['klinik']->id);
    expect(ClinicalFileModel::query()->count())->toBe(0)->and(ClinicalFileModel::withTrashed()->count())->toBe(1)
        ->and(AuditLogModel::query()->whereIn('action', ['encounter.file_uploaded', 'encounter.file_removed'])->count())->toBe(2);
    Storage::disk('private')->assertExists($f->path);
    test()->actingAs($s['dokter_user'])->get("/clinical-files/{$f->id}")->assertNotFound();
});

it('RM-08 izin dan isolasi: resepsionis ditolak, klinik lain tidak melihat berkas', function () {
    $a = siapKunjungan();
    unggahBerkas($a, UploadedFile::fake()->image('r.png'));
    tenant()->set($a['klinik']->id);
    $id = ClinicalFileModel::query()->firstOrFail()->id;
    $b = siapKunjungan();

    test()->actingAs($a['user'])->get("/clinical-files/{$id}")->assertForbidden();
    test()->actingAs($b['dokter_user'])->get("/clinical-files/{$id}")->assertNotFound();
    test()->actingAs($b['dokter_user'])->delete("/encounters/{$a['kunjungan']}/files/{$id}")->assertNotFound();
    test()->actingAs(makeUser(Role::Pemilik, $a['klinik']))->post("/encounters/{$a['kunjungan']}/files", ['file' => UploadedFile::fake()->image('r.png'), 'kind' => 'xray'])->assertForbidden();
});

it('RM-08 daftar berkas ikut dalam layar pemeriksaan', function () {
    $s = siapKunjungan();
    unggahBerkas($s, UploadedFile::fake()->image('r.png'), ['caption' => 'Panoramik']);

    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertInertia(fn ($p) => $p
        ->has('files', 1)->where('files.0.caption', 'Panoramik')->where('files.0.kind_label', 'Rontgen')->where('files.0.uploaded_by', $s['dokter_user']->name));
});

it('RM-11 linimasa memuat semua kunjungan terbaru dulu dengan diagnosa, tindakan, ringkasan, dan berkas', function () {
    $s = siapKunjungan();
    $p = tindakan($s, ['scope' => 'visit', 'default_price_amount' => 125000]);
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['assessment' => 'Pulpitis', 'plan' => 'PSA']);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd('K04.0'), 'tooth_fdi' => 46]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $p->id, 'quantity' => 1]);
    unggahBerkas($s, UploadedFile::fake()->image('r.png'), ['tooth_fdi' => 46]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close");
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/addenda", ['body' => 'Kontrol', 'reason' => 'Tindak lanjut']);

    majuKe('2025-06-23 09:00:00');
    $janji2 = bukaJanji($s, ['date' => '2025-06-23', 'time' => '10:00']);
    majuKe('2025-06-23 10:00:00');
    test()->actingAs($s['user'])->post("/appointments/{$janji2}/check-in");

    test()->actingAs($s['dokter_user'])->get("/patients/{$s['pasien']}/timeline")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Patients/Timeline')
        ->where('patient.name', 'Budi Santoso')
        ->has('encounters', 2)
        ->where('encounters.0.status', 'open')
        ->where('encounters.1.status', 'closed')
        ->where('encounters.1.assessment', 'Pulpitis')
        ->where('encounters.1.plan', 'PSA')
        ->where('encounters.1.addendum_count', 1)
        ->where('encounters.1.diagnoses.0.code', 'K04.0')
        ->where('encounters.1.procedures.0.unit_price_amount', 125000)
        ->has('encounters.1.files', 1)
        ->where('encounters.1.doctor_name', 'drg. Sari'));
});

it('RM-11 linimasa mencatat access log dan menolak klinik lain serta peran tanpa izin', function () {
    $a = siapKunjungan();
    $b = siapKunjungan();

    test()->actingAs($a['perawat'])->get("/patients/{$a['pasien']}/timeline")->assertOk();
    expect(DB::table('access_logs')->where('patient_id', $a['pasien'])->where('actor_id', $a['perawat']->id)->exists())->toBeTrue();
    test()->actingAs($b['dokter_user'])->get("/patients/{$a['pasien']}/timeline")->assertNotFound();
    test()->actingAs($a['user'])->get("/patients/{$a['pasien']}/timeline")->assertForbidden();
});
