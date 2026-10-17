<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Clinic\Infrastructure\Eloquent\BranchModel;
use Src\Clinic\Infrastructure\Eloquent\ChairModel;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Clinic\Infrastructure\Eloquent\DoctorModel;
use Src\Clinic\Infrastructure\Eloquent\DoctorScheduleModel;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\IdentityAccess\Infrastructure\Seeders\RolePermissionSeeder;
use Src\Scheduling\Infrastructure\Eloquent\AppointmentModel;
use Src\Shared\Application\TenantContext;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Pembantu tes (data sintetis saja, M12)
|--------------------------------------------------------------------------
*/

function seedRoles(): void
{
    app(RolePermissionSeeder::class)->run();
}

function makeClinic(string $name = 'Klinik Uji'): ClinicModel
{
    $clinic = new ClinicModel;
    $clinic->forceFill(['name' => $name, 'timezone' => 'Asia/Jakarta'])->save();

    return $clinic;
}

function makeBranch(ClinicModel $clinic, string $name = 'Cabang Utama'): BranchModel
{
    $branch = new BranchModel;
    $branch->forceFill(['clinic_id' => $clinic->id, 'name' => $name, 'is_active' => true])->save();

    return $branch;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeUser(Role $role = Role::Admin, ?ClinicModel $clinic = null, ?BranchModel $branch = null, array $attributes = []): User
{
    $clinic ??= makeClinic();
    static $counter = 0;
    $counter++;

    $user = new User;
    $user->forceFill([
        'clinic_id' => $clinic->id,
        'branch_id' => $branch?->id,
        'name' => "Pengguna Uji {$counter}",
        'email' => "uji{$counter}@contoh.test",
        'username' => "uji{$counter}",
        'password' => 'kata-sandi-uji-123',
        'is_active' => true,
        ...$attributes,
    ])->save();

    seedRoles();
    $user->assignRole($role->value);

    return $user;
}

function tenant(): TenantContext
{
    return app(TenantContext::class);
}

/**
 * Data pasien sintetis untuk tes pendaftaran dan impor (NIK fiktif berawalan 9999, M12).
 *
 * @param  array<string, mixed>  $o
 * @return array<string, mixed>
 */
function payloadPasien(array $o = []): array
{
    return [
        'name' => 'Budi Santoso',
        'nik' => '9999010101900001',
        'birth_date' => '1990-01-01',
        'sex' => 'male',
        'address' => 'Jl. Contoh No. 1',
        'phone' => '08123456789',
        'occupation' => 'Karyawan',
        'emergency_name' => 'Ani Santoso',
        'emergency_relation' => 'Istri',
        'emergency_phone' => '08129876543',
        'consent' => true,
        ...$o,
    ];
}

/**
 * Dokter uji dengan jadwal praktik mingguan.
 *
 * @param  list<array{weekday: int, start: string, end: string}>  $schedule
 */
function makeDoctor(ClinicModel $clinic, string $name = 'drg. Uji', array $schedule = [], bool $active = true): DoctorModel
{
    tenant()->set($clinic->id);
    $doctor = new DoctorModel;
    $doctor->forceFill(['name' => $name, 'is_active' => $active, 'fee_scheme' => null])->save();
    foreach ($schedule as $slot) {
        (new DoctorScheduleModel)->forceFill(['doctor_id' => $doctor->id, 'weekday' => $slot['weekday'], 'start_time' => $slot['start'].':00', 'end_time' => $slot['end'].':00'])->save();
    }

    return $doctor;
}

function makeChair(ClinicModel $clinic, string $name = 'Kursi Uji', bool $active = true): ChairModel
{
    tenant()->set($clinic->id);
    $chair = new ChairModel;
    $chair->forceFill(['name' => $name, 'is_active' => $active])->save();

    return $chair;
}

/**
 * Pasien sintetis langsung ke tabel (tanpa melewati pendaftaran) untuk tes modul lain.
 */
function makePatient(ClinicModel $clinic, string $name = 'Pasien Uji', string $birthDate = '1990-01-01'): string
{
    static $n = 0;
    $n++;
    $id = strtoupper((string) Str::ulid());
    DB::table('patients')->insert([
        'id' => $id, 'clinic_id' => $clinic->id, 'medical_record_no' => sprintf('UJI-%05d', $n), 'name' => $name,
        'name_search' => mb_strtolower($name), 'birth_date' => $birthDate, 'sex' => 'male', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

// Senin dan Selasa pada pekan uji; zona waktu klinik Asia/Jakarta (UTC+7).
const SENIN = '2025-06-16';
const SELASA = '2025-06-17';

/** Memajukan jam tes; sesi dikosongkan agar batas tidak aktif (AUTH-04) tidak memutus pengguna tes. */
function majuKe(string $waktu): void
{
    Carbon::setTestNow(Carbon::parse($waktu, 'Asia/Jakarta'));
    test()->flushSession();
}

/**
 * @return array{klinik: ClinicModel, user: object, dokter: object, kursi: object, pasien: string}
 */
function siapJadwal(Role $peran = Role::Resepsionis): array
{
    $klinik = makeClinic();
    $dokter = makeDoctor($klinik, 'drg. Sari', [
        ['weekday' => 1, 'start' => '09:00', 'end' => '12:00'],
        ['weekday' => 1, 'start' => '14:00', 'end' => '17:00'],
        ['weekday' => 2, 'start' => '09:00', 'end' => '12:00'],
    ]);

    return [
        'klinik' => $klinik,
        'user' => makeUser($peran, $klinik),
        'dokter' => $dokter,
        'kursi' => makeChair($klinik, 'Kursi 1'),
        'pasien' => makePatient($klinik, 'Budi Santoso'),
    ];
}

function janji(array $s, array $o = []): array
{
    return [
        'patient_id' => $s['pasien'],
        'doctor_id' => $s['dokter']->id,
        'chair_id' => $s['kursi']->id,
        'date' => SENIN,
        'time' => '09:00',
        'minutes' => 30,
        'complaint' => 'Sakit gigi',
        ...$o,
    ];
}

function bukaJanji(array $s, array $o = []): string
{
    test()->actingAs($s['user'])->post('/appointments', janji($s, $o))->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);

    return AppointmentModel::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail()->id;
}
