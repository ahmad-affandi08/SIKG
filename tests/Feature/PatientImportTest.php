<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Src\IdentityAccess\Domain\Role;
use Src\Patient\Application\Import\ProcessImportChunk;
use Src\Patient\Infrastructure\Eloquent\ImportBatchModel;
use Src\Patient\Infrastructure\Eloquent\PatientMedicalHistoryModel;
use Src\Patient\Infrastructure\Eloquent\PatientModel;
use Src\Patient\Infrastructure\Import\XlsxSpreadsheetReader;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

const KOLOM_IMPOR = ['nama', 'tanggal_lahir', 'jenis_kelamin', 'nik', 'hp', 'alamat', 'pekerjaan', 'alergi', 'penyakit_sistemik', 'wali_nama', 'wali_hubungan', 'wali_hp'];

/**
 * @param  list<array<string, mixed>>  $baris
 * @param  list<string>  $kolom
 */
function berkasImpor(array $baris, array $kolom = KOLOM_IMPOR): UploadedFile
{
    $sheet = new Spreadsheet;
    $data = [$kolom];
    foreach ($baris as $b) {
        $data[] = array_map(fn ($k) => $b[$k] ?? null, $kolom);
    }
    $sheet->getActiveSheet()->fromArray($data);
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($sheet))->save($path);

    return new UploadedFile($path, 'pasien.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function barisPasien(array $o = []): array
{
    return ['nama' => 'Pasien Impor', 'tanggal_lahir' => '1990-01-31', 'jenis_kelamin' => 'L', 'nik' => '9999010131900001', 'hp' => '081200000001', ...$o];
}

function unggah(object $test, object $user, array $baris, array $kolom = KOLOM_IMPOR): string
{
    $test->actingAs($user)->post('/patients/import', ['file' => berkasImpor($baris, $kolom)])->assertSessionHasNoErrors();
    tenant()->set($user->clinic_id, $user->branch_id);

    return ImportBatchModel::query()->latest('created_at')->latest('id')->firstOrFail()->id;
}

beforeEach(function () {
    Storage::fake('private');
});

it('PAS-08 Admin mengunduh templat Excel dengan semua kolom', function () {
    $admin = makeUser(Role::Admin);

    $respons = $this->actingAs($admin)->get('/patients/import/template')->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="templat-impor-pasien.xlsx"');
    $isi = (string) $respons->getContent();

    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    file_put_contents($path, $isi);
    $judul = IOFactory::load($path)->getActiveSheet()->rangeToArray('A1:L1')[0];
    expect($judul)->toBe(XlsxSpreadsheetReader::HEADERS);
});

it('PAS-08 pratinjau memisahkan baris valid, galat, dan dugaan duplikat tanpa membuat pasien', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $this->actingAs($admin)->post('/patients', payloadPasien(['nik' => '9999010131900099', 'name' => 'Sudah Ada', 'birth_date' => '1980-05-05', 'phone' => '08111111111']));

    $batch = unggah($this, $admin, [
        barisPasien(['nama' => 'Valid Satu', 'nik' => '9999010131900011', 'hp' => '081200000011']),
        barisPasien(['nama' => 'Valid Dua', 'nik' => '9999010131900012', 'hp' => '081200000012', 'tanggal_lahir' => '15/03/1985', 'jenis_kelamin' => 'Perempuan']),
        barisPasien(['nama' => '', 'nik' => '9999010131900013']),
        barisPasien(['nama' => 'NIK Salah', 'nik' => '123']),
        barisPasien(['nama' => 'Anak Tanpa Wali', 'nik' => '9999010131900014', 'tanggal_lahir' => now()->subYears(8)->format('Y-m-d')]),
        barisPasien(['nama' => 'Nama Lain', 'nik' => '9999010131900099']),
        barisPasien(['nama' => 'Valid Satu Ganda', 'nik' => '9999010131900011', 'hp' => '081200000099']),
    ]);

    tenant()->set($klinik->id);
    $b = ImportBatchModel::query()->findOrFail($batch);
    expect($b->status)->toBe('previewed')
        ->and($b->total_rows)->toBe(7)
        ->and($b->valid_rows)->toBe(2)
        ->and($b->error_rows)->toBe(3)
        ->and($b->duplicate_rows)->toBe(2)
        ->and(array_column($b->errors, 'row'))->toBe([4, 5, 6])
        ->and($b->duplicate_row_numbers)->toBe([7, 8])
        ->and(PatientModel::query()->count())->toBe(1);
    expect(json_encode($b->errors))->not->toContain('9999010131900013')->not->toContain('Anak Tanpa Wali');
});

it('PAS-08 konfirmasi mengimpor baris valid saja dan dugaan duplikat dilewati', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $this->actingAs($admin)->post('/patients', payloadPasien(['nik' => '9999010131900099', 'name' => 'Sudah Ada', 'birth_date' => '1980-05-05', 'phone' => '08111111111']));
    $batch = unggah($this, $admin, [
        barisPasien(['nama' => 'Valid Satu', 'nik' => '9999010131900011', 'hp' => '081200000011', 'alergi' => 'Penisilin; Lateks', 'penyakit_sistemik' => 'Diabetes']),
        barisPasien(['nama' => 'Duplikat', 'nik' => '9999010131900099']),
        barisPasien(['nama' => '']),
    ]);

    $this->actingAs($admin)->post("/patients/import/{$batch}/commit")->assertRedirect();

    tenant()->set($klinik->id);
    $b = ImportBatchModel::query()->findOrFail($batch);
    expect($b->status)->toBe('completed')->and($b->imported_rows)->toBe(1)->and($b->skipped_rows)->toBe(2)
        ->and(PatientModel::query()->count())->toBe(2);
    $baru = PatientModel::query()->where('name', 'Valid Satu')->firstOrFail();
    expect($baru->medical_record_no)->toBe('RM-000002')
        ->and($baru->data_consent_at)->toBeNull()
        ->and(PatientMedicalHistoryModel::query()->where('patient_id', $baru->id)->count())->toBe(3)
        ->and(AuditLogModel::query()->whereIn('action', ['patient.imported', 'patient.import_completed'])->count())->toBe(2);
    Storage::disk('private')->assertMissing($b->file_path);
});

it('PAS-08 petugas dapat memilih ikut mengimpor dugaan duplikat', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $this->actingAs($admin)->post('/patients', payloadPasien(['nik' => '9999010131900099']));
    $batch = unggah($this, $admin, [barisPasien(['nama' => 'Sama NIK', 'nik' => '9999010131900099'])]);

    $this->actingAs($admin)->post("/patients/import/{$batch}/commit", ['include_duplicates' => true]);

    tenant()->set($klinik->id);
    expect(PatientModel::query()->count())->toBe(2);
});

it('PAS-08 baris tanpa NIK tidak ditolak tetapi ditandai perlu dilengkapi', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $batch = unggah($this, $admin, [barisPasien(['nama' => 'Tanpa NIK', 'nik' => null, 'hp' => '081200000021'])]);

    $this->actingAs($admin)->post("/patients/import/{$batch}/commit");

    tenant()->set($klinik->id);
    $pasien = PatientModel::query()->firstOrFail();
    expect($pasien->nik_encrypted)->toBeNull()->and($pasien->nik_missing_reason)->toBe('Perlu dilengkapi (hasil impor)');
});

it('PAS-08 berkas besar diproses per potongan dan penunjuk tidak mengulang baris', function () {
    config(['sikg.chunk_size' => 2]);
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $baris = [];
    for ($i = 1; $i <= 5; $i++) {
        $baris[] = barisPasien(['nama' => "Pasien Potongan {$i}", 'tanggal_lahir' => '1990-01-'.sprintf('%02d', 10 + $i), 'nik' => '99990101319001'.sprintf('%02d', 20 + $i), 'hp' => '0812000001'.sprintf('%02d', $i)]);
    }
    $batch = unggah($this, $admin, $baris);

    $this->actingAs($admin)->post("/patients/import/{$batch}/commit");

    tenant()->set($klinik->id);
    expect(PatientModel::query()->count())->toBe(5)
        ->and(PatientModel::query()->orderBy('medical_record_no')->pluck('medical_record_no')->all())->toBe(['RM-000001', 'RM-000002', 'RM-000003', 'RM-000004', 'RM-000005']);

    // job dijalankan ulang setelah selesai: tidak ada pasien ganda
    tenant()->set($klinik->id);
    app(ProcessImportChunk::class)($batch);
    expect(PatientModel::query()->count())->toBe(5);
});

it('PAS-08 konfirmasi dua kali ditolak', function () {
    $admin = makeUser(Role::Admin);
    $batch = unggah($this, $admin, [barisPasien()]);

    $this->actingAs($admin)->post("/patients/import/{$batch}/commit")->assertSessionHasNoErrors();
    $this->actingAs($admin)->post("/patients/import/{$batch}/commit")->assertSessionHasErrors('domain');
});

it('PAS-08 berkas ditolak bila melebihi batas baris, tanpa kolom wajib, atau bukan Excel', function () {
    config(['sikg.import_max_rows' => 2]);
    $admin = makeUser(Role::Admin);

    $this->actingAs($admin)->post('/patients/import', ['file' => berkasImpor([barisPasien(), barisPasien(['nik' => '9999010131900002']), barisPasien(['nik' => '9999010131900003'])])])
        ->assertSessionHasErrors('domain');
    $this->actingAs($admin)->post('/patients/import', ['file' => berkasImpor([['x' => 'a']], ['x', 'y'])])
        ->assertSessionHasErrors('domain');
    $this->actingAs($admin)->post('/patients/import', ['file' => UploadedFile::fake()->create('pasien.txt', 5, 'text/plain')])
        ->assertSessionHasErrors('file');
    $this->actingAs($admin)->post('/patients/import', ['file' => berkasImpor([])])
        ->assertSessionHasErrors('domain');

    expect(ImportBatchModel::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('PAS-08 berkas unggahan tersimpan terenkripsi di disk privat', function () {
    config(['sikg.encrypt_files' => true]);
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $batch = unggah($this, $admin, [barisPasien()]);

    $path = ImportBatchModel::query()->findOrFail($batch)->file_path;
    expect(str_starts_with(Storage::disk('private')->get($path), 'PK'))->toBeFalse();
});

it('PAS-08 hanya peran dengan izin impor; batch klinik lain tidak terlihat', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $adminA = makeUser(Role::Admin, $a);
    $batch = unggah($this, $adminA, [barisPasien()]);

    $this->actingAs(makeUser(Role::Resepsionis, $a))->get('/patients/import')->assertForbidden();
    $this->actingAs(makeUser(Role::Pemilik, $a))->get('/patients/import')->assertForbidden();
    $this->actingAs(makeUser(Role::Admin, $b))->get("/patients/import/{$batch}")->assertNotFound();
    $this->actingAs($adminA)->get("/patients/import/{$batch}")->assertOk()->assertInertia(fn ($p) => $p->component('Patients/Import')->where('batch.valid_rows', 1));
    expect(DB::table('import_batches')->count())->toBe(1);
});
