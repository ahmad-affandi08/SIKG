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
use Src\Clinic\Infrastructure\Eloquent\ProcedureModel;
use Src\Clinical\Infrastructure\Eloquent\DiagnosisModel;
use Src\Clinical\Infrastructure\Eloquent\EncounterModel;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\IdentityAccess\Infrastructure\Seeders\RolePermissionSeeder;
use Src\Pharmacy\Application\StockService;
use Src\Pharmacy\Infrastructure\Eloquent\MedicineModel;
use Src\Scheduling\Infrastructure\Eloquent\AppointmentModel;
use Src\Shared\Application\Notifications\EmailSender;
use Src\Shared\Application\Notifications\TelegramSender;
use Src\Shared\Application\PdfRenderer;
use Src\Shared\Application\PdfRequest;
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

/**
 * Janji yang sudah check-in sehingga kunjungan terbuat otomatis.
 *
 * @return array<string, mixed>
 */
function siapKunjungan(): array
{
    $s = siapJadwal();
    $janjiId = bukaJanji($s);
    test()->actingAs($s['user'])->post("/appointments/{$janjiId}/check-in")->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $kunjungan = EncounterModel::query()->where('appointment_id', $janjiId)->firstOrFail();

    return $s + [
        'janji' => $janjiId,
        'kunjungan' => $kunjungan->id,
        'dokter_user' => makeUser(Role::Dokter, $s['klinik']),
        'perawat' => makeUser(Role::Perawat, $s['klinik']),
    ];
}

function tindakan(array $s, array $o = []): ProcedureModel
{
    tenant()->set($s['klinik']->id);
    static $n = 0;
    $n++;
    $p = new ProcedureModel;
    $p->forceFill(['code' => "TDK-{$n}", 'name' => "Tindakan {$n}", 'scope' => 'tooth', 'default_price_amount' => 150000, 'is_active' => true, ...$o])->save();

    return $p;
}

function icd(string $kode = 'K02.1'): string
{
    return DiagnosisModel::query()->where('icd10_code', $kode)->firstOrFail()->id;
}

/**
 * Obat uji pada katalog klinik.
 *
 * @param  array<string, mixed>  $o
 */
function makeMedicine(ClinicModel $clinic, array $o = []): MedicineModel
{
    tenant()->set($clinic->id);
    static $n = 0;
    $n++;
    $m = new MedicineModel;
    $m->forceFill(['name' => "Obat Uji {$n}", 'unit' => 'tablet', 'sell_price_amount' => 5000, 'min_stock' => 10, 'drug_class' => null, 'is_active' => true, ...$o])->save();

    return $m;
}

function isiStok(ClinicModel $clinic, MedicineModel $m, int $jumlah): void
{
    tenant()->set($clinic->id);
    app(StockService::class)->receive(makeUser(Role::Admin, $clinic)->id, $m->id, $jumlah, null, null, 'Stok awal uji');
}

/**
 * ADR-0008: perekam permintaan PDF. Tes memeriksa data yang dikirim ke templat (kop) tanpa harus membaca isi PDF.
 */
final class PdfPerekam implements PdfRenderer
{
    /** @var list<PdfRequest> */
    public static array $permintaan = [];

    public function render(PdfRequest $request): string
    {
        self::$permintaan[] = $request;

        return '%PDF-perekam';
    }
}

function rekamPdf(): void
{
    PdfPerekam::$permintaan = [];
    app()->instance(PdfRenderer::class, new PdfPerekam);
}

/**
 * Mengisi kop klinik lengkap (alamat, izin, teks tambahan, catatan kaki) dan mengembalikan kop yang harus tampil di SEMUA cetakan.
 *
 * @return array{name: string, address: string, license: string, header: string, logo: null, footnote: string, city: string}
 */
function isiKop(ClinicModel $klinik): array
{
    $kop = [
        'name' => $klinik->name,
        'address' => 'Jl. Slamet Riyadi No. 123, Surakarta',
        'license' => 'Izin Klinik No. 445/2024',
        'header' => 'Telp. (0271) 123456 · Senin sampai Sabtu 09.00-20.00',
        'logo' => null,
        'footnote' => 'Obat dan pembayaran yang sudah diterima tidak dapat dikembalikan.',
        'city' => 'Surakarta',
    ];
    DB::table('clinics')->where('id', $klinik->id)->update(['address' => $kop['address'], 'license_no' => $kop['license'], 'receipt_header' => $kop['header'], 'prescription_header' => $kop['footnote'], 'city' => $kop['city']]);

    return $kop;
}

/**
 * SYS-05, JDW-07: perekam pengiriman email dan Telegram. `configured` mensimulasikan ada tidaknya konfigurasi saluran.
 */
final class KirimPerekam
{
    /** @var list<array{to: string, subject: string, body: string}> */
    public static array $email = [];

    /** @var list<array{chat: string, text: string}> */
    public static array $telegram = [];

    public static bool $emailOn = true;

    public static bool $telegramOn = true;

    public static bool $gagal = false;
}

final class EmailPerekam implements EmailSender
{
    public function configured(): bool
    {
        return KirimPerekam::$emailOn;
    }

    public function send(string $to, string $subject, string $body): bool
    {
        if (KirimPerekam::$gagal) {
            return false;
        }
        KirimPerekam::$email[] = ['to' => $to, 'subject' => $subject, 'body' => $body];

        return true;
    }
}

final class TelegramPerekam implements TelegramSender
{
    public function configured(): bool
    {
        return KirimPerekam::$telegramOn;
    }

    public function send(string $chatId, string $text): bool
    {
        if (KirimPerekam::$gagal) {
            return false;
        }
        KirimPerekam::$telegram[] = ['chat' => $chatId, 'text' => $text];

        return true;
    }
}

function rekamKirim(bool $email = true, bool $telegram = true): void
{
    KirimPerekam::$email = [];
    KirimPerekam::$telegram = [];
    KirimPerekam::$emailOn = $email;
    KirimPerekam::$telegramOn = $telegram;
    KirimPerekam::$gagal = false;
    app()->instance(EmailSender::class, new EmailPerekam);
    app()->instance(TelegramSender::class, new TelegramPerekam);
}
