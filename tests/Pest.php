<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
use Src\Shared\Application\Notifications\StaffWhatsApp;
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
 * SYS-05, JDW-07: perekam pengiriman WhatsApp staf. `configured` mensimulasikan ada tidaknya saluran WhatsApp yang lengkap.
 */
final class KirimPerekam
{
    /** @var list<array{phone: string, title: string, body: string}> */
    public static array $whatsapp = [];

    public static bool $whatsappOn = true;

    public static bool $gagal = false;
}

final class WhatsAppStafPerekam implements StaffWhatsApp
{
    public function configured(): bool
    {
        return KirimPerekam::$whatsappOn;
    }

    public function send(string $phone, string $title, string $body): bool
    {
        if (KirimPerekam::$gagal) {
            return false;
        }
        KirimPerekam::$whatsapp[] = ['phone' => $phone, 'title' => $title, 'body' => $body];

        return true;
    }
}

function rekamKirim(bool $whatsapp = true): void
{
    KirimPerekam::$whatsapp = [];
    KirimPerekam::$whatsappOn = $whatsapp;
    KirimPerekam::$gagal = false;
    app()->instance(StaffWhatsApp::class, new WhatsAppStafPerekam);
}

/**
 * Membangun berkas DICOM sintetis (M12: tanpa data pasien nyata) untuk menguji pembaca.
 *
 * @param  array<string, mixed>  $o
 */
function dicomSintetis(array $o = []): string
{
    $syntax = $o['syntax'] ?? '1.2.840.10008.1.2.1';
    $rows = $o['rows'] ?? 2;
    $cols = $o['cols'] ?? 4;
    $bits = $o['bits'] ?? 16;
    $pixels = $o['pixels'] ?? [0, 100, 200, 300, 400, 500, 600, 1000];
    $big = $syntax === '1.2.840.10008.1.2.2';
    $implicit = $syntax === '1.2.840.10008.1.2';
    $photometric = $o['photometric'] ?? 'MONOCHROME2';

    $u16 = fn (int $v, bool $b = false) => pack($b ? 'n' : 'v', $v);
    $pad = fn (string $s, string $fill = ' ') => strlen($s) % 2 === 1 ? $s.$fill : $s;
    $long = ['OB', 'OW', 'SQ', 'UN', 'UT'];
    $element = function (int $g, int $e, string $vr, string $value, bool $b, bool $imp) use ($u16, $long): string {
        $tag = $u16($g, $b).$u16($e, $b);
        if ($imp) {
            return $tag.pack($b ? 'N' : 'V', strlen($value)).$value;
        }
        if (in_array($vr, $long, true)) {
            return $tag.$vr."\0\0".pack($b ? 'N' : 'V', strlen($value)).$value;
        }

        return $tag.$vr.$u16(strlen($value), $b).$value;
    };

    $meta = $element(0x0002, 0x0010, 'UI', $pad($syntax, "\0"), false, false);
    $meta = $element(0x0002, 0x0000, 'UL', pack('V', strlen($meta)), false, false).$meta;

    $data = '';
    $data .= $element(0x0008, 0x0020, 'DA', '20250616', $big, $implicit);
    $data .= $element(0x0008, 0x0060, 'CS', 'IO', $big, $implicit);
    if (isset($o['patient_id'])) {
        $data .= $element(0x0010, 0x0020, 'LO', $pad($o['patient_id']), $big, $implicit);
    }
    if (isset($o['sequence'])) {
        // Urutan (SQ) berpanjang tak tentu berisi satu item berpanjang tak tentu dengan elemen di dalamnya.
        $inner = $element(0x0008, 0x1140, 'UI', $pad('1.2.3', "\0"), $big, $implicit);
        $item = $u16(0xFFFE, $big).$u16(0xE000, $big).pack($big ? 'N' : 'V', 0xFFFFFFFF).$inner.$u16(0xFFFE, $big).$u16(0xE00D, $big).pack($big ? 'N' : 'V', 0);
        $seq = $u16(0x0008, $big).$u16(0x1111, $big).($implicit ? '' : 'SQ'."\0\0").pack($big ? 'N' : 'V', 0xFFFFFFFF).$item.$u16(0xFFFE, $big).$u16(0xE0DD, $big).pack($big ? 'N' : 'V', 0);
        $data .= $seq;
    }
    $data .= $element(0x0028, 0x0002, 'US', $u16(1, $big), $big, $implicit);
    $data .= $element(0x0028, 0x0004, 'CS', $pad($photometric), $big, $implicit);
    $data .= $element(0x0028, 0x0010, 'US', $u16($rows, $big), $big, $implicit);
    $data .= $element(0x0028, 0x0011, 'US', $u16($cols, $big), $big, $implicit);
    $data .= $element(0x0028, 0x0100, 'US', $u16($bits, $big), $big, $implicit);
    $data .= $element(0x0028, 0x0101, 'US', $u16($bits, $big), $big, $implicit);
    $data .= $element(0x0028, 0x0103, 'US', $u16($o['signed'] ?? 0, $big), $big, $implicit);
    if (isset($o['window'])) {
        $data .= $element(0x0028, 0x1050, 'DS', $pad((string) $o['window'][0]), $big, $implicit);
        $data .= $element(0x0028, 0x1051, 'DS', $pad((string) $o['window'][1]), $big, $implicit);
    }
    $raw = $bits === 8 ? pack('C*', ...$pixels) : pack($big ? 'n*' : 'v*', ...array_map(fn (int $v) => $v < 0 ? $v + 65536 : $v, $pixels));
    $data .= $element(0x7FE0, 0x0010, $bits === 8 ? 'OB' : 'OW', $raw, $big, $implicit);

    return str_repeat("\0", 128).'DICM'.$meta.$data;
}

/**
 * @param  list<list<string|int|float|null>>  $rows  baris pertama = judul kolom
 */
function xlsxImpor(array $rows): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray($rows);
    $stream = fopen('php://temp', 'r+');
    (new Xlsx($book))->save($stream);
    rewind($stream);

    return UploadedFile::fake()->createWithContent('impor.xlsx', (string) stream_get_contents($stream));
}

function tokenDariPengalihan($respons): string
{
    $lokasi = (string) $respons->headers->get('Location');

    return substr($lokasi, strrpos($lokasi, '/') + 1);
}
