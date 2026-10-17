<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Application\Backup\BackupEngine;
use Src\Shared\Application\Backup\BackupFailed;
use Src\Shared\Application\Backup\BackupService;
use Src\Shared\Infrastructure\Backup\BackupKey;
use Src\Shared\Infrastructure\Backup\BackupReader;
use Src\Shared\Infrastructure\Backup\BackupRunModel;
use Src\Shared\Infrastructure\Mail\BackupAlertMail;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-17 09:00:00', 'UTC'));
    $this->akar = sys_get_temp_dir().'/sikg-uji-cadangan-'.uniqid();
    config([
        'filesystems.disks.backup.root' => $this->akar.'/cadangan',
        'filesystems.disks.private.root' => $this->akar.'/private',
        'sikg.backup_interval_hours' => 24,
        'sikg.backup_key' => null,
    ]);
    Storage::forgetDisk(['backup', 'private']);
    app()->forgetInstance(BackupEngine::class);
    app()->forgetInstance(BackupService::class);
});

afterEach(function () {
    Carbon::setTestNow();
    File::deleteDirectory($this->akar);
});

function cadangan(): BackupService
{
    return app(BackupService::class);
}

/** Cadangan baru dengan konfigurasi saat ini (mengosongkan instance singleton yang menyimpan konfigurasi lama). */
function segarkanCadangan(): void
{
    Storage::forgetDisk(['backup', 'private']);
    app()->forgetInstance(BackupEngine::class);
    app()->forgetInstance(BackupService::class);
}

/** @return array{klinik: object, admin: object, pasien: string} */
function isiData(): array
{
    $klinik = makeClinic('Klinik Cadangan');
    $admin = makeUser(Role::Admin, $klinik);
    $pasien = makePatient($klinik, 'Budi Rahasiawan');
    Storage::disk('private')->put("clinical/{$pasien}/rontgen.bin", 'isi-berkas-terenkripsi-aplikasi');
    Storage::disk('private')->put('clinic/logo.png', 'logo');

    return ['klinik' => $klinik, 'admin' => $admin, 'pasien' => $pasien];
}

it('SYS-02 cadangan membuat berkas terenkripsi tanpa teks biasa dan mencatat jalannya', function () {
    $d = isiData();

    $hasil = cadangan()->backup();

    expect($hasil['status'])->toBe('ok')->and($hasil['file'])->toBe('sikg-20261017-090000.sikgbak');
    $isi = Storage::disk('backup')->get($hasil['file']);
    expect(str_starts_with($isi, 'SIKGBAK1'))->toBeTrue()
        ->and(str_contains($isi, 'Budi Rahasiawan'))->toBeFalse()
        ->and(str_contains($isi, $d['pasien']))->toBeFalse()
        ->and(str_contains($isi, 'patients'))->toBeFalse();

    $log = BackupRunModel::query()->where('kind', 'backup')->firstOrFail();
    expect($log->ok)->toBeTrue()->and($log->file)->toBe($hasil['file'])->and($log->details['tables'])->toBeGreaterThan(10)->and($log->details['rows'])->toBeGreaterThan(0)
        ->and($log->details['sha256'])->toBe(hash('sha256', $isi));
});

it('SYS-02 tabel volatil dan catatan cadangan tidak ikut, data aplikasi ikut', function () {
    isiData();
    cadangan()->backup();

    $bytes = Storage::disk('backup')->get('sikg-20261017-090000.sikgbak');
    $stream = fopen('php://memory', 'w+');
    fwrite($stream, $bytes);
    rewind($stream);
    $tabel = [];
    foreach ((new BackupReader($stream, BackupKey::resolve(null, config('app.key'))))->records() as $r) {
        if ($r['t'] === 'table') {
            $tabel[] = $r['n'];
        }
    }

    expect($tabel)->toContain('patients', 'users', 'audit_logs', 'invoices', 'payments', 'sequences', 'migrations')
        ->and($tabel)->not->toContain('sessions', 'cache', 'jobs', 'failed_jobs', 'backup_runs', 'system_heartbeats');
});

it('SYS-02 berkas unggahan disalin ke cermin dan tidak disalin ulang bila tidak berubah', function () {
    $d = isiData();

    $pertama = cadangan()->backup();
    expect($pertama['files'])->toBe(['files' => 2, 'copied' => 2, 'missing' => 0])
        ->and(Storage::disk('backup')->get("files/clinical/{$d['pasien']}/rontgen.bin"))->toBe('isi-berkas-terenkripsi-aplikasi');

    $kedua = cadangan()->backup(true);
    expect($kedua['files'])->toBe(['files' => 2, 'copied' => 0, 'missing' => 0]);

    Storage::disk('private')->put('clinic/baru.txt', 'tambahan');
    expect(cadangan()->backup(true)['files'])->toBe(['files' => 3, 'copied' => 1, 'missing' => 0]);
});

it('SYS-02 cadangan hanya berjalan bila jatuh tempo menurut interval, tahan cron terlewat', function () {
    isiData();

    expect(cadangan()->backup()['status'])->toBe('ok')
        ->and(cadangan()->backup()['status'])->toBe('skipped');

    Carbon::setTestNow(Carbon::parse('2026-10-18 08:59:00', 'UTC'));
    expect(cadangan()->backup()['status'])->toBe('skipped');

    Carbon::setTestNow(Carbon::parse('2026-10-18 09:00:01', 'UTC'));
    expect(cadangan()->backup()['status'])->toBe('ok')
        ->and(count(app(BackupEngine::class)->backups()))->toBe(2);

    // Cron mati beberapa hari lalu hidup lagi: satu cadangan saja, tidak menumpuk.
    Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:00', 'UTC'));
    expect(cadangan()->backup()['status'])->toBe('ok')->and(cadangan()->backup()['status'])->toBe('skipped');
});

it('SYS-02 uji pemulihan memulihkan ke tabel sementara dan cocok dengan manifest tanpa mengubah data aplikasi', function () {
    isiData();
    $hitung = fn () => [DB::table('patients')->count(), DB::table('users')->count(), DB::table('audit_logs')->count()];
    cadangan()->backup();
    $sebelum = $hitung();

    $hasil = cadangan()->verify();

    expect($hasil['status'])->toBe('ok')->and($hasil['message'])->toContain('Uji pemulihan berhasil')
        ->and($hitung())->toBe($sebelum)
        ->and(Schema::hasTable('zrt_patients'))->toBeFalse();
    $log = BackupRunModel::query()->where('kind', 'verify')->firstOrFail();
    expect($log->ok)->toBeTrue()->and($log->details['rows'])->toBeGreaterThan(0)->and($log->file)->toBe('sikg-20261017-090000.sikgbak');
});

it('SYS-02 uji pemulihan gagal bila cadangan diubah atau kunci berbeda dan dicatat sebagai gagal', function () {
    isiData();
    cadangan()->backup();
    $nama = 'sikg-20261017-090000.sikgbak';
    $asli = Storage::disk('backup')->get($nama);

    Storage::disk('backup')->put($nama, substr_replace($asli, chr(ord($asli[100]) ^ 1), 100, 1));
    $rusak = cadangan()->verify($nama);
    expect($rusak['status'])->toBe('failed')->and($rusak['message'])->toContain('diubah');

    Storage::disk('backup')->put($nama, $asli);
    config(['sikg.backup_key' => 'kunci-cadangan-lain-yang-panjang']);
    segarkanCadangan();
    expect(cadangan()->verify($nama)['status'])->toBe('failed');

    expect(BackupRunModel::query()->where('kind', 'verify')->where('ok', false)->count())->toBe(2);
});

it('SYS-02 uji pemulihan tanpa cadangan dan nama berkas tidak sah ditolak', function () {
    expect(cadangan()->verify()['status'])->toBe('failed')->and(cadangan()->verify()['message'])->toContain('Belum ada cadangan');

    $tidakSah = cadangan()->verify('../../rahasia');
    expect($tidakSah['status'])->toBe('failed')->and($tidakSah['message'])->toContain('tidak sah');
});

it('SYS-02 pemulihan menolak basis data yang berisi dan skema yang berbeda; tidak menghapus apa pun', function () {
    isiData();
    cadangan()->backup();
    $jumlah = DB::table('patients')->count();

    expect(fn () => cadangan()->restore('sikg-20261017-090000.sikgbak'))->toThrow(BackupFailed::class, 'tidak kosong');
    expect(DB::table('patients')->count())->toBe($jumlah);

    DB::table('migrations')->insert(['migration' => '2099_01_01_000000_masa_depan', 'batch' => 999]);
    expect(fn () => cadangan()->restore('sikg-20261017-090000.sikgbak'))->toThrow(BackupFailed::class, 'Skema basis data berbeda');
});

it('SYS-02 perintah restore wajib --confirm dan menolak basis data berisi', function () {
    isiData();
    cadangan()->backup();

    $this->artisan('sikg:backup:restore', ['file' => 'sikg-20261017-090000.sikgbak'])->expectsOutputToContain('--confirm')->assertFailed();
    $this->artisan('sikg:backup:restore', ['file' => 'sikg-20261017-090000.sikgbak', '--confirm' => true])->expectsOutputToContain('tidak kosong')->assertFailed();
});

it('SYS-02 pemangkasan membuang cadangan lama sesuai retensi dan mencatatnya', function () {
    isiData();
    $disk = Storage::disk('backup');
    foreach (['sikg-20260301-020000', 'sikg-20260320-020000', 'sikg-20260901-020000', 'sikg-20261016-020000'] as $nama) {
        $disk->put($nama.'.sikgbak', 'x');
    }

    $hasil = cadangan()->prune();

    expect($hasil['status'])->toBe('ok')->and($hasil['removed'])->toBe(['sikg-20260320-020000.sikgbak'])
        ->and($disk->exists('sikg-20260301-020000.sikgbak'))->toBeTrue()  // tertua bulannya, dalam 12 bulan
        ->and($disk->exists('sikg-20260320-020000.sikgbak'))->toBeFalse()
        ->and($disk->exists('sikg-20260901-020000.sikgbak'))->toBeTrue()
        ->and($disk->exists('sikg-20261016-020000.sikgbak'))->toBeTrue()
        ->and(BackupRunModel::query()->where('kind', 'prune')->where('ok', true)->exists())->toBeTrue();
});

it('SYS-02 catatan cadangan hanya bertambah', function () {
    isiData();
    cadangan()->backup();
    $run = BackupRunModel::query()->firstOrFail();

    expect(fn () => $run->forceFill(['ok' => false])->save())->toThrow(LogicException::class)
        ->and(fn () => $run->delete())->toThrow(LogicException::class);
});

it('SYS-02 status: belum ada cadangan, sehat, uji pemulihan basi, terlambat, dan gagal', function () {
    isiData();
    expect(cadangan()->status()['state'])->toBe('warn')->and(cadangan()->status()['reasons'][0])->toContain('Belum pernah');

    cadangan()->backup();
    $s = cadangan()->status();
    expect($s['state'])->toBe('warn')->and($s['reasons'][0])->toContain('Uji pemulihan belum berhasil');

    cadangan()->verify();
    expect(cadangan()->status()['state'])->toBe('ok');

    Carbon::setTestNow(Carbon::parse('2026-10-27 09:00:00', 'UTC'));
    $basi = cadangan()->status();
    expect($basi['state'])->toBe('fail')->and($basi['reasons'][0])->toContain('lebih tua dari dua kali interval');

    Carbon::setTestNow(Carbon::parse('2026-10-17 10:00:00', 'UTC'));
    config(['filesystems.disks.backup.root' => '/proc/tidak-bisa-ditulis']);
    segarkanCadangan();
    expect(cadangan()->backup(true)['status'])->toBe('failed');
    $gagal = cadangan()->status();
    expect($gagal['state'])->toBe('fail')->and(implode(' ', $gagal['reasons']))->toContain('Cadangan terakhir gagal');
});

it('SYS-02 pesan galat tak terduga tidak membocorkan isi (hanya jenisnya)', function () {
    isiData();
    config(['filesystems.disks.backup.root' => '/proc/tidak-bisa-ditulis']);
    segarkanCadangan();

    $hasil = cadangan()->backup(true);

    expect($hasil['status'])->toBe('failed')->and($hasil['message'])->not->toContain('/proc')->and($hasil['message'])->not->toContain('Budi');
});

it('SYS-02 pemeriksaan kesehatan mengirim surel saat cadangan bermasalah, sekali per perubahan', function () {
    Mail::fake();
    config(['sikg.alert_email' => 'teknis@klinik.test']);
    isiData();
    cadangan()->backup();
    cadangan()->verify();
    Carbon::setTestNow(Carbon::parse('2026-10-27 09:00:00', 'UTC'));

    Artisan::call('sikg:health-check');
    Artisan::call('sikg:health-check');

    Mail::assertSent(BackupAlertMail::class, 1);
    Mail::assertSent(BackupAlertMail::class, fn ($m) => $m->hasTo('teknis@klinik.test') && str_contains(implode(' ', $m->reasons), 'dua kali interval'));
});

it('SYS-02 halaman Sistem menampilkan keadaan cadangan untuk Admin', function () {
    $d = isiData();
    cadangan()->backup();
    cadangan()->verify();

    $this->actingAs($d['admin'])->get('/settings/system')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/System')
        ->where('backup.state', 'ok')->where('backup.backups', 1)->where('backup.interval_hours', 24)
        ->where('backup.last_backup.file', 'sikg-20261017-090000.sikgbak')->has('backup.recent', 3));
});

it('SYS-02 scheduler mendaftarkan cadangan, uji pemulihan mingguan, dan pemangkasan', function () {
    $nama = collect(app(Schedule::class)->events())->map(fn ($e) => $e->description)->all();

    expect($nama)->toContain('sikg-backup', 'sikg-backup-verify', 'sikg-backup-prune');
});

it('SYS-02 perintah sikg:backup dan sikg:backup:verify berjalan dari konsol', function () {
    isiData();

    $this->artisan('sikg:backup', ['--force' => true])->expectsOutputToContain('Cadangan selesai')->assertSuccessful();
    $this->artisan('sikg:backup')->expectsOutputToContain('Belum jatuh tempo')->assertSuccessful();
    $this->artisan('sikg:backup:verify')->expectsOutputToContain('Uji pemulihan berhasil')->assertSuccessful();
    $this->artisan('sikg:backup:prune')->assertSuccessful();
});
