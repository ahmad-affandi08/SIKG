<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\IdentityAccess\Domain\Role;
use Src\Patient\Infrastructure\Eloquent\PatientModel;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Semua kelas model Eloquent aplikasi (src/*\/Infrastructure), ditemukan dari berkasnya.
 *
 * @return list<class-string<Model>>
 */
function semuaModel(): array
{
    $kelas = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('src'))) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_contains($file->getPathname(), '/Infrastructure/') || ! (str_contains($file->getPathname(), '/Eloquent/') || str_ends_with($file->getFilename(), 'Model.php'))) {
            continue;
        }
        $nama = 'Src\\'.str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen(base_path('src')) + 1));
        if (class_exists($nama) && is_subclass_of($nama, Model::class) && ! (new ReflectionClass($nama))->isAbstract()) {
            $kelas[] = $nama;
        }
    }

    return $kelas;
}

function lunak(string $kelas): bool
{
    return in_array(SoftDeletes::class, class_uses_recursive($kelas), true);
}

it('SYS-03 setiap model aplikasi dapat dihapus lunak atau bersifat hanya-tambah, tidak ada yang dihapus permanen', function () {
    $model = semuaModel();
    expect(count($model))->toBeGreaterThan(30);

    foreach ($model as $kelas) {
        $contoh = new $kelas;
        $contoh->exists = true;
        $contoh->setAttribute($contoh->getKeyName(), 'UJI');

        // forceDelete selalu ditolak sebelum menyentuh basis data.
        expect(fn () => $contoh->forceDelete())->toThrow(LogicException::class);

        if (! lunak($kelas)) {
            // Model hanya-tambah menolak delete() sama sekali.
            expect(fn () => $contoh->delete())->toThrow(LogicException::class);
        }
    }
});

it('SYS-03 setiap tabel bertenant memiliki model yang aman dan setiap tabel data klinis atau keuangan terklasifikasi', function () {
    $tabelModel = [];
    foreach (semuaModel() as $kelas) {
        $tabelModel[(new $kelas)->getTable()] = $kelas;
    }

    // Tabel tanpa deleted_at harus sengaja hanya-tambah (atau turunan yang diturunkan dari induk yang dihapus lunak).
    $hanyaTambah = [
        'access_logs', 'audit_logs', 'cash_closings', 'payments', 'stock_movements', 'installment_plans', 'installments',
        'clinical_notes', 'patient_consents', 'odontogram_tooth_states', 'prescription_items', 'stock_balances',
        'backup_runs', 'diagnoses', 'report_jobs',
    ];
    $sistem = ['cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations', 'model_has_permissions', 'model_has_roles', 'permissions', 'role_has_permissions', 'roles', 'sequences', 'sessions', 'system_heartbeats'];

    $tanpaKlasifikasi = [];
    foreach (Schema::getTables() as $tabel) {
        $nama = (string) $tabel['name'];
        if (in_array($nama, $sistem, true)) {
            continue;
        }
        $kelas = $tabelModel[$nama] ?? null;
        $punyaKolom = Schema::hasColumn($nama, 'deleted_at');
        if ($kelas === null) {
            $tanpaKlasifikasi[] = "{$nama} (tanpa model)";

            continue;
        }
        if ($punyaKolom !== lunak($kelas)) {
            $tanpaKlasifikasi[] = "{$nama} (kolom deleted_at dan SoftDeletes tidak selaras)";
        }
        if (! $punyaKolom && ! in_array($nama, $hanyaTambah, true)) {
            $tanpaKlasifikasi[] = "{$nama} (tanpa deleted_at dan belum didaftarkan hanya-tambah)";
        }
    }

    expect($tanpaKlasifikasi)->toBe([]);
});

it('SYS-03 semua foreign key memakai ON DELETE RESTRICT sehingga induk berdata tidak dapat terhapus', function () {
    $aturan = DB::select('select constraint_name, table_name, delete_rule from information_schema.referential_constraints where constraint_schema = database()');

    expect(count($aturan))->toBeGreaterThan(50);
    $melanggar = array_values(array_filter($aturan, fn ($r) => ! in_array($r->delete_rule ?? $r->DELETE_RULE, ['RESTRICT', 'NO ACTION'], true)));
    expect($melanggar)->toBe([]);
});

it('SYS-03 pasien berkunjung tidak dapat dihapus dari basis data karena kunjungannya (RESTRICT)', function () {
    $s = siapKunjungan();

    expect(fn () => DB::table('patients')->where('id', $s['pasien'])->delete())->toThrow(QueryException::class);
});

it('SYS-03 arsip pasien oleh Admin hanya mengisi deleted_at; kunjungan dan rekam medisnya tetap utuh dan tercatat di audit', function () {
    $s = siapKunjungan();
    $admin = makeUser(Role::Admin, $s['klinik']);

    $this->actingAs($admin)->delete("/patients/{$s['pasien']}", ['reason' => 'Pasien meninggal dunia'])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $pasien = PatientModel::withTrashed()->findOrFail($s['pasien']);
    expect($pasien->deleted_at)->not->toBeNull()
        ->and(PatientModel::query()->find($s['pasien']))->toBeNull()
        ->and(DB::table('encounters')->where('patient_id', $s['pasien'])->whereNull('deleted_at')->count())->toBe(1)
        ->and(AuditLogModel::query()->where('subject_id', $s['pasien'])->where('action', 'like', 'patient.%')->count())->toBeGreaterThan(0);
});

it('SYS-03 penghapusan tindakan, diagnosa, dan berkas klinis dari kunjungan hanya soft delete', function () {
    $s = siapKunjungan();
    $tindakan = tindakan($s);
    $this->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $tindakan->id, 'tooth_fdi' => 46, 'quantity' => 1])->assertSessionHasNoErrors();
    $this->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()])->assertSessionHasNoErrors();
    $proc = DB::table('encounter_procedures')->value('id');
    $diag = DB::table('encounter_diagnoses')->value('id');

    $this->actingAs($s['dokter_user'])->delete("/encounters/{$s['kunjungan']}/procedures/{$proc}")->assertRedirect();
    $this->actingAs($s['dokter_user'])->delete("/encounters/{$s['kunjungan']}/diagnoses/{$diag}")->assertRedirect();

    expect(DB::table('encounter_procedures')->where('id', $proc)->value('deleted_at'))->not->toBeNull()
        ->and(DB::table('encounter_diagnoses')->where('id', $diag)->value('deleted_at'))->not->toBeNull();
});

it('SYS-03 tidak ada kode aplikasi yang menghapus permanen: setiap delete() terdaftar dan hanya pada model hapus-lunak', function () {
    $diizinkan = [
        // berkas => jumlah panggilan yang sah (model hapus-lunak atau berkas sementara/disk, bukan data klinis dan keuangan)
        'src/Clinic/Infrastructure/EloquentClinicSettingsService.php' => 1,   // hari libur (soft delete)
        'src/Clinic/Infrastructure/EloquentDoctorAdministration.php' => 1,    // jadwal praktik dokter (soft delete)
        'src/Clinic/Infrastructure/EloquentClinicProfileService.php' => 2,    // berkas logo di disk, bukan data pasien
        'src/Clinical/Infrastructure/EloquentEncounterRepository.php' => 2,   // tindakan dan diagnosa kunjungan (soft delete)
        'src/Clinical/Infrastructure/EloquentClinicalFileRepository.php' => 1, // baris berkas klinis (soft delete); isi berkas tetap
        'src/Patient/Infrastructure/EloquentPatientRepository.php' => 3,      // arsip pasien, riwayat medis, wali (soft delete)
        'src/Patient/Infrastructure/Import/PrivateImportFileStore.php' => 2,  // berkas impor sementara di disk
        'src/Patient/Application/Import/ProcessImportChunk.php' => 1,         // berkas impor sementara via port
        'src/Patient/Application/Import/StartPatientImport.php' => 1,         // berkas impor sementara via port
        'src/Billing/Infrastructure/EloquentInvoiceRepository.php' => 1,      // item tagihan (soft delete)
        'src/Reporting/Application/ReportExportService.php' => 1,             // berkas ekspor laporan kedaluwarsa via port; baris report_jobs tetap
        'src/Reporting/Infrastructure/PrivateReportFileStore.php' => 1,       // berkas ekspor laporan di disk privat, bukan data aplikasi
        'src/Shared/Infrastructure/Backup/PhpBackupEngine.php' => 1,          // cadangan lama sesuai retensi, bukan data aplikasi
    ];

    $ditemukan = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('src'))) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Migrations/')) {
            continue;
        }
        $kode = (string) file_get_contents($file->getPathname());
        $jumlah = preg_match_all('/->delete\(|->forceDelete\(|->truncate\(|::truncate\(|forceDeleteQuietly\(/', $kode);
        $relatif = substr($file->getPathname(), strlen(base_path()) + 1);
        if ($jumlah > 0 && ! str_ends_with($relatif, 'RefusesHardDelete.php')) {
            $ditemukan[$relatif] = $jumlah;
        }
    }
    ksort($ditemukan);
    ksort($diizinkan);

    expect($ditemukan)->toBe($diizinkan);
});
