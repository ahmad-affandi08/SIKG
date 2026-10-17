<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Spatie\Permission\PermissionRegistrar;
use Src\IdentityAccess\Domain\Role;
use Src\Reporting\Application\ReportExportService;
use Src\Reporting\Application\ReportJobStore;
use Src\Reporting\Application\ReportRenderer;
use Src\Reporting\Domain\ExportFormat;
use Src\Reporting\Domain\ReportResult;

/*
 * LAP-01 sampai LAP-10: laporan dihitung di server dari data sintetis yang dimasukkan langsung ke tabel sumber (M12).
 * Semua waktu di bawah memakai zona klinik Asia/Jakarta (UTC+7); SENIN adalah hari data uji.
 */

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-17 08:00:00', 'Asia/Jakarta'));
    Storage::fake('private');
});

afterEach(function () {
    Carbon::setTestNow();
});

function lapId(): string
{
    return strtoupper((string) Str::ulid());
}

/** Waktu lokal klinik ke UTC untuk disimpan. */
function lapUtc(string $local): string
{
    return Carbon::parse($local, 'Asia/Jakarta')->utc()->format('Y-m-d H:i:s');
}

/**
 * Data uji satu klinik dengan dua dokter. Ringkasan (Senin, lokal):
 * - A (drg. Sari, pasien baru): CABUT Rp 150.000, tagihan lunas tunai Rp 150.000; janji selesai, check-in 08:50, anamnesis 09:05 (tunggu 15 mnt).
 * - B (drg. Sari, pasien lama): TAMBAL 2 x Rp 100.000 + obat Rp 30.000, diskon Rp 30.000, total Rp 200.000, dibayar QRIS Rp 50.000; tanpa diagnosa.
 * - C (drg. Sari, pasien baru): masih terbuka, tanpa isi.
 * - D (drg. Sari, pasien baru): tagihan dibatalkan Rp 100.000; tanpa diagnosa dan tindakan.
 * - F (drg. Sari, pasien baru): tagihan draft dengan uang muka tunai Rp 25.000.
 * - G (drg. Budi, pasien baru): SCALING Rp 100.000, tanpa tagihan dan tanpa diagnosa.
 * Janji: satu selesai, satu tidak datang, satu batal.
 *
 * @return array<string, mixed>
 */
function lapData(): array
{
    $k = makeClinic();
    tenant()->set($k->id);
    $now = now();
    $u = [
        'pemilik' => makeUser(Role::Pemilik, $k), 'admin' => makeUser(Role::Admin, $k), 'kasir' => makeUser(Role::Kasir, $k),
        'dokter' => makeUser(Role::Dokter, $k), 'dokter2' => makeUser(Role::Dokter, $k), 'perawat' => makeUser(Role::Perawat, $k), 'resepsionis' => makeUser(Role::Resepsionis, $k),
    ];
    $sari = makeDoctor($k, 'drg. Sari');
    $budi = makeDoctor($k, 'drg. Budi');
    DB::table('doctors')->where('id', $sari->id)->update(['user_id' => $u['dokter']->id, 'fee_scheme' => json_encode(['default' => ['mode' => 'percentage', 'value' => 4000], 'overrides' => ['TAMBAL' => ['mode' => 'fixed', 'value' => 50000]]])]);
    DB::table('doctors')->where('id', $budi->id)->update(['user_id' => $u['dokter2']->id]);

    $metode = fn (string $nama, string $jenis) => tap(lapId(), fn (string $id) => DB::table('payment_methods')->insert(['id' => $id, 'clinic_id' => $k->id, 'name' => $nama, 'type' => $jenis, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]));
    $tunai = $metode('Tunai', 'cash');
    $qris = $metode('QRIS', 'qris');

    $prosedur = fn (string $kode, string $nama, ?string $kategori, int $harga) => tap(lapId(), fn (string $id) => DB::table('procedures')->insert(['id' => $id, 'clinic_id' => $k->id, 'code' => $kode, 'name' => $nama, 'category' => $kategori, 'scope' => 'tooth', 'default_price_amount' => $harga, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]));
    $cabut = $prosedur('CABUT', 'Pencabutan gigi', 'Bedah', 150000);
    $tambal = $prosedur('TAMBAL', 'Tambal gigi', 'Restorasi', 100000);
    $scaling = $prosedur('SCALING', 'Scaling', null, 100000);

    $pasien = [];
    foreach (['a' => 'Pasien A', 'b' => 'Pasien B', 'c' => 'Pasien C', 'd' => 'Pasien D', 'f' => 'Pasien F', 'g' => 'Pasien G'] as $kode => $nama) {
        $pasien[$kode] = makePatient($k, $nama);
    }

    $janji = function (string $pasienId, string $dokterId, string $status, string $mulai, ?string $checkIn) use ($k, $now): string {
        $id = lapId();
        DB::table('appointments')->insert([
            'id' => $id, 'clinic_id' => $k->id, 'patient_id' => $pasienId, 'doctor_id' => $dokterId, 'starts_at' => lapUtc($mulai), 'ends_at' => lapUtc($mulai), 'status' => $status,
            'source' => 'booking', 'checked_in_at' => $checkIn === null ? null : lapUtc($checkIn), 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $id;
    };
    $janjiA = $janji($pasien['a'], $sari->id, 'completed', '2025-06-16 09:00', '2025-06-16 08:50');
    $janji($pasien['c'], $sari->id, 'no_show', '2025-06-16 10:30', null);
    $janji($pasien['d'], $sari->id, 'cancelled', '2025-06-16 11:30', null);

    // Kunjungan lama pasien B sebelum periode (menjadikannya pasien lama pada Senin).
    $kunjungan = function (string $pasienId, string $dokterId, string $mulai, string $status, ?string $janjiId = null, ?string $anamnesis = null) use ($k, $now): string {
        $id = lapId();
        DB::table('encounters')->insert([
            'id' => $id, 'clinic_id' => $k->id, 'patient_id' => $pasienId, 'appointment_id' => $janjiId, 'doctor_id' => $dokterId, 'started_at' => lapUtc($mulai),
            'closed_at' => $status === 'closed' ? lapUtc($mulai) : null, 'status' => $status, 'anamnesis_at' => $anamnesis === null ? null : lapUtc($anamnesis), 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $id;
    };
    $kunjungan($pasien['b'], $sari->id, '2025-05-01 09:00', 'closed');

    $a = $kunjungan($pasien['a'], $sari->id, '2025-06-16 09:00', 'closed', $janjiA, '2025-06-16 09:05');
    $b = $kunjungan($pasien['b'], $sari->id, '2025-06-16 10:00', 'closed');
    $c = $kunjungan($pasien['c'], $sari->id, '2025-06-16 11:00', 'open');
    $d = $kunjungan($pasien['d'], $sari->id, '2025-06-16 12:00', 'closed');
    $f = $kunjungan($pasien['f'], $sari->id, '2025-06-16 13:00', 'open');
    $g = $kunjungan($pasien['g'], $budi->id, '2025-06-16 14:00', 'closed');

    $tindakanKunjungan = function (string $kunjunganId, string $prosedurId, string $kode, string $nama, int $jumlah, int $harga, string $user) use ($k, $now): string {
        $id = lapId();
        DB::table('encounter_procedures')->insert([
            'id' => $id, 'clinic_id' => $k->id, 'encounter_id' => $kunjunganId, 'procedure_id' => $prosedurId, 'procedure_code' => $kode, 'procedure_name' => $nama, 'scope' => 'tooth',
            'quantity' => $jumlah, 'unit_price_amount' => $harga, 'performed_by' => $user, 'performed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $id;
    };
    $epA = $tindakanKunjungan($a, $cabut, 'CABUT', 'Pencabutan gigi', 1, 150000, $u['dokter']->id);
    $epB = $tindakanKunjungan($b, $tambal, 'TAMBAL', 'Tambal gigi', 2, 100000, $u['dokter']->id);
    $epG = $tindakanKunjungan($g, $scaling, 'SCALING', 'Scaling', 1, 100000, $u['dokter2']->id);
    // Waktu tindakan harus Senin lokal.
    DB::table('encounter_procedures')->update(['performed_at' => lapUtc('2025-06-16 10:00')]);

    $diagnosa = function (string $kunjunganId) use ($k, $now): void {
        DB::table('encounter_diagnoses')->insert(['id' => lapId(), 'clinic_id' => $k->id, 'encounter_id' => $kunjunganId, 'diagnosis_id' => icd(), 'icd10_code' => 'K02.1', 'diagnosis_name' => 'Karies dentin', 'created_at' => $now, 'updated_at' => $now]);
    };
    $diagnosa($a);
    $catatan = function (string $kunjunganId, string $penulis, string $waktu) use ($k, $now): void {
        DB::table('clinical_notes')->insert(['id' => lapId(), 'clinic_id' => $k->id, 'encounter_id' => $kunjunganId, 'type' => 'S', 'body' => 'Catatan uji', 'author_id' => $penulis, 'authored_at' => lapUtc($waktu), 'created_at' => $now]);
    };
    $catatan($a, $u['dokter']->id, '2025-06-16 09:10');

    $tagihan = function (string $kunjunganId, string $pasienId, string $lifecycle, string $status, ?string $nomor, int $subtotal, int $diskon, int $total, int $dibayar, ?string $terbit, ?string $batal = null) use ($k, $now): string {
        $id = lapId();
        DB::table('invoices')->insert([
            'id' => $id, 'clinic_id' => $k->id, 'number' => $nomor, 'patient_id' => $pasienId, 'encounter_id' => $kunjunganId, 'lifecycle' => $lifecycle, 'status' => $status,
            'subtotal_amount' => $subtotal, 'discount_amount' => $diskon, 'total_amount' => $total, 'paid_amount' => $dibayar, 'tax_amount' => 0, 'tax_basis_points' => 0,
            'issued_at' => $terbit === null ? null : lapUtc($terbit), 'voided_at' => $batal === null ? null : lapUtc($batal), 'void_reason' => $batal === null ? null : 'Salah input', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $id;
    };
    $invA = $tagihan($a, $pasien['a'], 'issued', 'paid', 'INV-2025-00001', 150000, 0, 150000, 150000, '2025-06-16 09:30');
    $invB = $tagihan($b, $pasien['b'], 'issued', 'partial', 'INV-2025-00002', 230000, 30000, 200000, 50000, '2025-06-16 10:30');
    $invD = $tagihan($d, $pasien['d'], 'void', 'void', 'INV-2025-00003', 100000, 0, 100000, 0, '2025-06-16 12:30', '2025-06-16 13:30');
    $invF = $tagihan($f, $pasien['f'], 'draft', 'draft', null, 0, 0, 0, 0, null);

    $item = function (string $invoiceId, string $jenis, ?string $sumber, string $deskripsi, int $jumlah, int $harga, int $total) use ($k, $now): void {
        DB::table('invoice_items')->insert(['id' => lapId(), 'clinic_id' => $k->id, 'invoice_id' => $invoiceId, 'source_type' => $jenis, 'source_id' => $sumber, 'description' => $deskripsi, 'quantity' => $jumlah, 'unit_price_amount' => $harga, 'discount_amount' => 0, 'total_amount' => $total, 'created_at' => $now, 'updated_at' => $now]);
    };
    $item($invA, 'procedure', $epA, 'CABUT Pencabutan gigi', 1, 150000, 150000);
    $item($invB, 'procedure', $epB, 'TAMBAL Tambal gigi', 2, 100000, 200000);
    $item($invB, 'medicine', lapId(), 'Obat uji', 1, 30000, 30000);

    $bayar = function (string $invoiceId, string $metodeId, string $jenis, int $jumlah, string $waktu, string $penerima, string $nomor) use ($k, $now): string {
        $id = lapId();
        DB::table('payments')->insert(['id' => $id, 'clinic_id' => $k->id, 'invoice_id' => $invoiceId, 'payment_method_id' => $metodeId, 'type' => $jenis, 'amount' => $jumlah, 'paid_at' => lapUtc($waktu), 'received_by' => $penerima, 'idempotency_key' => 'k-'.$nomor, 'receipt_no' => $nomor, 'created_at' => $now]);

        return $id;
    };
    $bayarA = $bayar($invA, $tunai, 'pay', 150000, '2025-06-16 09:30', $u['kasir']->id, 'KW-1');
    $bayar($invB, $qris, 'pay', 50000, '2025-06-16 10:30', $u['kasir']->id, 'KW-2');
    $bayar($invF, $tunai, 'dp', 25000, '2025-06-16 13:10', $u['kasir']->id, 'KW-3');

    $penutupan = lapId();
    DB::table('cash_closings')->insert(['id' => $penutupan, 'clinic_id' => $k->id, 'closing_date' => '2025-06-16', 'cashier_id' => $u['kasir']->id, 'system_amount' => 150000, 'counted_amount' => 140000, 'difference_amount' => -10000, 'note' => 'Uang kembalian kurang', 'closed_at' => lapUtc('2025-06-16 17:00'), 'created_at' => $now]);
    DB::table('payments')->where('id', $bayarA)->update(['cash_closing_id' => $penutupan]);

    // Stok: obat 1 di bawah minimum dengan batch hampir kedaluwarsa; obat 2 cukup.
    $obat1 = makeMedicine($k, ['name' => 'Amoksisilin', 'min_stock' => 10]);
    $obat2 = makeMedicine($k, ['name' => 'Parasetamol', 'min_stock' => 5]);
    foreach ([[$obat1, 3], [$obat2, 50]] as [$obat, $jumlah]) {
        DB::table('stock_balances')->insert(['id' => lapId(), 'clinic_id' => $k->id, 'medicine_id' => $obat->id, 'quantity' => $jumlah, 'created_at' => $now, 'updated_at' => $now]);
    }
    $gerak = fn (string $obat, string $jenis, int $jumlah, int $saldo, ?string $batch, ?string $kedaluwarsa, ?string $alasan, string $oleh, string $waktu) => [
        'id' => lapId(), 'clinic_id' => $k->id, 'medicine_id' => $obat, 'type' => $jenis, 'quantity' => $jumlah, 'balance_after' => $saldo, 'reason' => $alasan,
        'batch_no' => $batch, 'expiry_date' => $kedaluwarsa, 'created_by' => $oleh, 'created_at' => lapUtc($waktu),
    ];
    DB::table('stock_movements')->insert([
        $gerak($obat1->id, 'in', 20, 20, 'B-01', '2025-06-30', null, $u['perawat']->id, '2025-06-16 08:00'),
        $gerak($obat1->id, 'out', -17, 3, null, null, 'Resep', $u['dokter']->id, '2025-06-16 11:00'),
        $gerak($obat2->id, 'in', 50, 50, 'B-09', '2027-01-01', null, $u['perawat']->id, '2025-06-16 08:05'),
    ]);

    // Audit dan jejak akses.
    foreach ([[$u['pemilik'], 'invoice.voided'], [$u['pemilik'], 'invoice.voided'], [$u['admin'], 'user.created'], [$u['dokter'], 'encounter.closed']] as [$pelaku, $aksi]) {
        DB::table('audit_logs')->insert(['id' => lapId(), 'clinic_id' => $k->id, 'actor_id' => $pelaku->id, 'action' => $aksi, 'subject_type' => 'x', 'subject_id' => lapId(), 'created_at' => lapUtc('2025-06-16 15:00')]);
    }
    foreach ([$pasien['a'], $pasien['a'], $pasien['b']] as $p) {
        DB::table('access_logs')->insert(['id' => lapId(), 'clinic_id' => $k->id, 'actor_id' => $u['dokter']->id, 'patient_id' => $p, 'route' => 'patients.show', 'at' => lapUtc('2025-06-16 09:00')]);
    }

    return compact('k', 'u', 'sari', 'budi', 'tunai', 'qris', 'pasien', 'invA', 'invB', 'invD', 'invF', 'obat1') + ['senin' => ['from' => SENIN, 'to' => SENIN]];
}

/**
 * @param  array<string, mixed>  $d
 * @param  array<string, string>  $query
 * @return array<string, mixed>
 */
function lapLihat(array $d, string $peran, string $laporan, array $query = []): array
{
    $query += $d['senin'];
    tenant()->set($d['k']->id);
    $respons = test()->actingAs($d['u'][$peran])->get("/reports/{$laporan}?".http_build_query($query));
    $respons->assertOk();
    $hasil = [];
    $respons->assertInertia(function ($p) use (&$hasil) {
        $hasil = $p->toArray()['props'];

        return $p;
    });

    return $hasil['report'];
}

/**
 * @param  array<string, mixed>  $laporan
 */
function lapMetrik(array $laporan, string $label): int|string|null
{
    foreach ($laporan['metrics'] as $m) {
        if ($m['label'] === $label) {
            return $m['value'];
        }
    }
    throw new RuntimeException("Metrik '{$label}' tidak ada");
}

/**
 * @param  array<string, mixed>  $laporan
 * @return array<string, mixed>
 */
function lapTabel(array $laporan, string $judul): array
{
    foreach ($laporan['tables'] as $t) {
        if ($t['title'] === $judul) {
            return $t;
        }
    }
    throw new RuntimeException("Tabel '{$judul}' tidak ada");
}

// --- LAP-01 ---------------------------------------------------------------------------------------------------------

it('LAP-01 ringkasan harian memuat penerimaan per metode, kunjungan, pasien baru, tindakan terbanyak, dan piutang baru', function () {
    $d = lapData();
    $r = lapLihat($d, 'pemilik', 'daily-summary');

    expect(lapMetrik($r, 'Penerimaan bersih'))->toBe(225000)
        ->and(lapMetrik($r, 'Pendapatan (tagihan terbit)'))->toBe(350000)
        ->and(lapMetrik($r, 'Piutang baru'))->toBe(150000)
        ->and(lapMetrik($r, 'Tagihan terbit'))->toBe(2)
        ->and(lapMetrik($r, 'Tagihan dibatalkan'))->toBe(1)
        ->and(lapMetrik($r, 'Jumlah kunjungan'))->toBe(6)
        ->and(lapMetrik($r, 'Pasien baru'))->toBe(5);

    $metode = lapTabel($r, 'Penerimaan per metode bayar');
    expect(array_column($metode['rows'], 'net', 'method'))->toBe(['QRIS' => 50000, 'Tunai' => 175000])
        ->and($metode['totals']['net'])->toBe(225000);

    $tindakan = lapTabel($r, 'Tindakan terbanyak');
    expect(array_column($tindakan['rows'], 'qty', 'code'))->toBe(['TAMBAL' => 2, 'CABUT' => 1, 'SCALING' => 1]);
});

it('LAP-01 kasir hanya melihat penerimaan yang ia catat dan tanpa angka tagihan klinik', function () {
    $d = lapData();
    $lain = makeUser(Role::Kasir, $d['k']);
    DB::table('payments')->where('receipt_no', 'KW-2')->update(['received_by' => $lain->id]);

    $r = lapLihat($d, 'kasir', 'daily-summary');

    expect(lapMetrik($r, 'Penerimaan bersih'))->toBe(175000)
        ->and(lapMetrik($r, 'Transaksi pembayaran Anda'))->toBe(2);
    $label = array_column($r['metrics'], 'label');
    expect($label)->not->toContain('Pendapatan (tagihan terbit)')->not->toContain('Piutang baru');
    expect(array_keys(lapTabel($r, 'Tindakan terbanyak')['rows'][0]))->not->toContain('value');
});

it('LAP-01 filter dokter dan metode bayar menyaring laporan', function () {
    $d = lapData();
    $budi = lapLihat($d, 'pemilik', 'daily-summary', ['doctor_id' => $d['budi']->id]);
    expect(lapMetrik($budi, 'Penerimaan bersih'))->toBe(0)->and(lapMetrik($budi, 'Jumlah kunjungan'))->toBe(1);

    $qris = lapLihat($d, 'pemilik', 'daily-summary', ['method_id' => $d['qris']]);
    expect(lapMetrik($qris, 'Penerimaan bersih'))->toBe(50000)->and(lapMetrik($qris, 'Pendapatan (tagihan terbit)'))->toBe(200000);
});

// --- LAP-02 ---------------------------------------------------------------------------------------------------------

it('LAP-02 pendapatan hanya dari tagihan final dan tagihan batal tampil terpisah', function () {
    $d = lapData();
    $r = lapLihat($d, 'admin', 'revenue');

    expect(lapMetrik($r, 'Pendapatan (tagihan terbit)'))->toBe(350000)
        ->and(lapMetrik($r, 'Penerimaan bersih'))->toBe(225000)
        ->and(lapMetrik($r, 'Diskon'))->toBe(30000)
        ->and(lapMetrik($r, 'Tagihan dibatalkan'))->toBe(1);

    $harian = lapTabel($r, 'Pendapatan per hari');
    expect($harian['rows'])->toHaveCount(1)->and($harian['rows'][0]['period'])->toBe(SENIN)->and($harian['totals']['total'])->toBe(350000);

    $dokter = lapTabel($r, 'Pendapatan per dokter');
    expect(array_column($dokter['rows'], 'total', 'doctor'))->toBe(['drg. Sari' => 350000]);

    $kategori = lapTabel($r, 'Pendapatan per kategori tindakan');
    expect(array_column($kategori['rows'], 'total', 'category'))->toBe(['Bedah' => 150000, 'Obat' => 30000, 'Restorasi' => 200000, 'Diskon tagihan' => -30000])
        ->and($kategori['totals']['total'])->toBe(350000);

    $batal = lapTabel($r, 'Tagihan dibatalkan (tidak masuk pendapatan)');
    expect($batal['rows'])->toHaveCount(1)->and($batal['rows'][0]['total'])->toBe(100000)->and($batal['rows'][0]['reason'])->toBe('Salah input');
});

it('LAP-02 dapat disajikan bulanan dan difilter per dokter', function () {
    $d = lapData();
    $bulanan = lapLihat($d, 'pemilik', 'revenue', ['from' => '2025-06-01', 'to' => '2025-06-30', 'grain' => 'month']);
    $tabel = lapTabel($bulanan, 'Pendapatan per bulan');
    expect($tabel['rows'])->toHaveCount(1)->and($tabel['rows'][0]['period'])->toBe('2025-06')->and($tabel['rows'][0]['total'])->toBe(350000);

    $budi = lapLihat($d, 'pemilik', 'revenue', ['doctor_id' => $d['budi']->id]);
    expect(lapMetrik($budi, 'Pendapatan (tagihan terbit)'))->toBe(0);
});

it('LAP-02 rentang di luar periode data tidak memuat apa pun dan batas hari memakai zona klinik', function () {
    $d = lapData();
    // Tagihan A terbit Senin 09:30 WIB = 02:30 UTC; tagihan lain pukul 00:30 WIB Selasa (Senin 17:30 UTC) harus masuk Selasa.
    DB::table('invoices')->where('id', $d['invA'])->update(['issued_at' => lapUtc('2025-06-17 00:30')]);
    $senin = lapLihat($d, 'pemilik', 'revenue');
    $selasa = lapLihat($d, 'pemilik', 'revenue', ['from' => SELASA, 'to' => SELASA]);

    expect(lapMetrik($senin, 'Pendapatan (tagihan terbit)'))->toBe(200000)
        ->and(lapMetrik($selasa, 'Pendapatan (tagihan terbit)'))->toBe(150000);
});

// --- LAP-03 ---------------------------------------------------------------------------------------------------------

it('LAP-03 jasa dokter dihitung dari skema di master dokter', function () {
    $d = lapData();
    $r = lapLihat($d, 'pemilik', 'doctor-fees');

    // CABUT 40% x 150.000 = 60.000; TAMBAL tetap Rp 50.000 x 2 = 100.000.
    expect(lapMetrik($r, 'Total jasa dokter'))->toBe(160000)
        ->and(lapMetrik($r, 'Nilai tindakan (dasar hitung)'))->toBe(350000);
    $rincian = lapTabel($r, 'Rincian per tindakan');
    expect(array_column($rincian['rows'], 'fee', 'code'))->toBe(['CABUT' => 60000, 'TAMBAL' => 100000])
        ->and(array_column($rincian['rows'], 'rule', 'code'))->toBe(['CABUT' => '40%', 'TAMBAL' => 'Rp 50.000 per tindakan']);
    expect(lapTabel($r, 'Jasa per dokter')['rows'])->toHaveCount(1);
});

// --- LAP-04 ---------------------------------------------------------------------------------------------------------

it('LAP-04 kunjungan memuat pasien baru dan lama, janji tidak datang, dan waktu tunggu', function () {
    $d = lapData();
    $r = lapLihat($d, 'admin', 'visits');

    expect(lapMetrik($r, 'Jumlah kunjungan'))->toBe(6)
        ->and(lapMetrik($r, 'Pasien baru'))->toBe(5)
        ->and(lapMetrik($r, 'Pasien lama'))->toBe(1)
        ->and(lapMetrik($r, 'Janji tidak datang'))->toBe(1)
        ->and(lapMetrik($r, 'Tingkat tidak datang'))->toBe(5000)
        ->and(lapMetrik($r, 'Rata-rata waktu tunggu'))->toBe(15);

    $harian = lapTabel($r, 'Kunjungan per hari');
    expect($harian['rows'][0])->toMatchArray(['period' => SENIN, 'visits' => 6, 'new' => 5, 'returning' => 1, 'no_show' => 1]);
    $perDokter = lapTabel($r, 'Per dokter');
    expect(array_column($perDokter['rows'], 'visits', 'doctor'))->toBe(['drg. Sari' => 5, 'drg. Budi' => 1]);
});

// --- LAP-05 ---------------------------------------------------------------------------------------------------------

it('LAP-05 tindakan: frekuensi dan nilai per jenis, dan dokter hanya melihat tindakannya sendiri', function () {
    $d = lapData();
    $semua = lapLihat($d, 'pemilik', 'procedures');
    $tabel = lapTabel($semua, 'Frekuensi dan nilai per jenis tindakan');
    expect(array_column($tabel['rows'], 'value', 'code'))->toBe(['TAMBAL' => 200000, 'CABUT' => 150000, 'SCALING' => 100000])
        ->and($tabel['totals']['qty'])->toBe(4)->and($tabel['totals']['value'])->toBe(450000);
    expect(lapTabel($semua, 'Tindakan per dokter')['rows'])->toHaveCount(2);

    $sendiri = lapLihat($d, 'dokter', 'procedures');
    $tabelSendiri = lapTabel($sendiri, 'Frekuensi dan nilai per jenis tindakan');
    expect(array_column($tabelSendiri['rows'], 'code'))->toBe(['TAMBAL', 'CABUT']);
    expect(array_column($sendiri['tables'], 'title'))->not->toContain('Tindakan per dokter');

    $dokterLain = lapLihat($d, 'dokter2', 'procedures');
    expect(array_column(lapTabel($dokterLain, 'Frekuensi dan nilai per jenis tindakan')['rows'], 'code'))->toBe(['SCALING']);
});

it('LAP-05 dokter tanpa profil dokter tidak melihat tindakan siapa pun', function () {
    $d = lapData();
    $tanpaProfil = makeUser(Role::Dokter, $d['k']);
    $d['u']['tanpa'] = $tanpaProfil;
    $r = lapLihat($d, 'tanpa', 'procedures');
    expect(lapTabel($r, 'Frekuensi dan nilai per jenis tindakan')['rows'])->toBe([]);
});

// --- LAP-06 ---------------------------------------------------------------------------------------------------------

it('LAP-06 piutang: tagihan belum lunas, umur piutang, dan uang muka belum terpakai', function () {
    $d = lapData();
    $r = lapLihat($d, 'pemilik', 'receivables', ['from' => '2025-06-01', 'to' => '2025-06-30']);

    expect(lapMetrik($r, 'Tagihan belum lunas'))->toBe(1)
        ->and(lapMetrik($r, 'Total piutang'))->toBe(150000)
        ->and(lapMetrik($r, 'Uang muka belum terpakai'))->toBe(25000);

    $umur = lapTabel($r, 'Umur piutang');
    expect(array_column($umur['rows'], 'balance', 'bucket'))->toBe(['0-30 hari' => 150000, '31-60 hari' => 0, '61-90 hari' => 0, 'Lebih dari 90 hari' => 0]);
    $daftar = lapTabel($r, 'Tagihan belum lunas');
    expect($daftar['rows'][0])->toMatchArray(['number' => 'INV-2025-00002', 'balance' => 150000, 'age' => 1, 'status' => 'Dibayar sebagian']);
    expect(lapTabel($r, 'Uang muka belum terpakai')['rows'][0])->toMatchArray(['method' => 'Tunai', 'amount' => 25000]);

    $kasir = lapLihat($d, 'kasir', 'receivables', ['from' => '2025-06-01', 'to' => '2025-06-30']);
    expect(lapMetrik($kasir, 'Total piutang'))->toBe(150000);
});

it('LAP-06 umur piutang masuk kelompok sesuai hari sejak terbit', function () {
    $d = lapData();
    DB::table('invoices')->where('id', $d['invB'])->update(['issued_at' => lapUtc('2025-03-10 10:00')]);
    $r = lapLihat($d, 'pemilik', 'receivables', ['from' => '2025-01-01', 'to' => '2025-06-30']);
    $umur = lapTabel($r, 'Umur piutang');
    expect(array_column($umur['rows'], 'balance', 'bucket')['Lebih dari 90 hari'])->toBe(150000);
});

// --- LAP-07 ---------------------------------------------------------------------------------------------------------

it('LAP-07 stok: saat ini, di bawah minimum, hampir kedaluwarsa, dan pergerakan', function () {
    $d = lapData();
    $r = lapLihat($d, 'perawat', 'stock');

    expect(lapMetrik($r, 'Jenis obat dan bahan'))->toBe(2)
        ->and(lapMetrik($r, 'Di bawah minimum'))->toBe(1)
        ->and(lapMetrik($r, 'Batch hampir kedaluwarsa'))->toBe(1);

    $sekarang = lapTabel($r, 'Stok saat ini');
    expect(array_column($sekarang['rows'], 'status', 'name'))->toBe(['Amoksisilin' => 'Di bawah minimum', 'Parasetamol' => 'Cukup']);
    $rendah = lapTabel($r, 'Di bawah stok minimum');
    expect($rendah['rows'][0])->toMatchArray(['name' => 'Amoksisilin', 'shortage' => 7]);
    $kedaluwarsa = lapTabel($r, 'Hampir kedaluwarsa atau sudah kedaluwarsa');
    expect($kedaluwarsa['rows'][0])->toMatchArray(['name' => 'Amoksisilin', 'batch' => 'B-01', 'expiry' => '2025-06-30', 'days_left' => 13]);
    $gerak = lapTabel($r, 'Pergerakan stok per obat');
    expect(array_column($gerak['rows'], 'net', 'name'))->toBe(['Amoksisilin' => 3, 'Parasetamol' => 50]);
    expect(lapTabel($r, 'Riwayat pergerakan stok')['rows'])->toHaveCount(3);
});

// --- LAP-08 ---------------------------------------------------------------------------------------------------------

it('LAP-08 tutup kas per kasir per hari memuat sistem, fisik, dan selisih', function () {
    $d = lapData();
    $r = lapLihat($d, 'pemilik', 'cash-closing');

    expect(lapMetrik($r, 'Penutupan kas'))->toBe(1)
        ->and(lapMetrik($r, 'Tunai menurut sistem'))->toBe(150000)
        ->and(lapMetrik($r, 'Uang fisik'))->toBe(140000)
        ->and(lapMetrik($r, 'Selisih'))->toBe(-10000)
        ->and(lapMetrik($r, 'Penutupan dengan selisih'))->toBe(1);
    $tabel = lapTabel($r, 'Penutupan kas per kasir per hari');
    expect($tabel['rows'][0])->toMatchArray(['date' => SENIN, 'system' => 150000, 'counted' => 140000, 'difference' => -10000, 'note' => 'Uang kembalian kurang']);
    $belum = lapTabel($r, 'Uang tunai belum ditutup');
    expect($belum['rows'][0]['amount'])->toBe(25000);
});

it('LAP-08 kasir hanya melihat penutupan miliknya', function () {
    $d = lapData();
    $lain = makeUser(Role::Kasir, $d['k']);
    DB::table('cash_closings')->insert(['id' => lapId(), 'clinic_id' => $d['k']->id, 'closing_date' => SENIN, 'cashier_id' => $lain->id, 'system_amount' => 99000, 'counted_amount' => 99000, 'difference_amount' => 0, 'closed_at' => now(), 'created_at' => now()]);

    $semua = lapLihat($d, 'pemilik', 'cash-closing');
    $sendiri = lapLihat($d, 'kasir', 'cash-closing');

    expect(lapMetrik($semua, 'Penutupan kas'))->toBe(2)->and(lapMetrik($sendiri, 'Penutupan kas'))->toBe(1)->and(lapMetrik($sendiri, 'Tunai menurut sistem'))->toBe(150000);
});

// --- LAP-09 ---------------------------------------------------------------------------------------------------------

it('LAP-09 kelengkapan rekam medis: tanpa diagnosa, tindakan, catatan, dan belum ditutup', function () {
    $d = lapData();
    $r = lapLihat($d, 'admin', 'record-completeness');

    // Ditutup: A, B, D, G. Lengkap (diagnosa dan tindakan): hanya A.
    expect(lapMetrik($r, 'Kunjungan ditutup'))->toBe(4)
        ->and(lapMetrik($r, 'Kelengkapan (diagnosa dan tindakan)'))->toBe(2500)
        ->and(lapMetrik($r, 'Tanpa diagnosa'))->toBe(3)
        ->and(lapMetrik($r, 'Tanpa tindakan'))->toBe(1)
        ->and(lapMetrik($r, 'Tanpa catatan'))->toBe(3)
        ->and(lapMetrik($r, 'Belum ditutup dokter'))->toBe(2);

    $rincian = lapTabel($r, 'Kunjungan yang perlu dilengkapi');
    expect($rincian['rows'])->toHaveCount(5);
    $byPasien = array_column($rincian['rows'], 'missing', 'patient');
    expect($byPasien['Pasien B'])->toBe('Diagnosa, Catatan')
        ->and($byPasien['Pasien D'])->toBe('Diagnosa, Tindakan, Catatan')
        ->and($byPasien['Pasien C'])->toBe('Belum ditutup dokter')
        ->and($rincian['rows'][0])->not->toHaveKey('Pasien A');
});

it('LAP-09 dokter hanya melihat kunjungannya sendiri', function () {
    $d = lapData();
    $r = lapLihat($d, 'dokter2', 'record-completeness');

    expect(lapMetrik($r, 'Kunjungan ditutup'))->toBe(1)->and(lapMetrik($r, 'Tanpa diagnosa'))->toBe(1);
    expect(array_column(lapTabel($r, 'Kunjungan yang perlu dilengkapi')['rows'], 'patient'))->toBe(['Pasien G']);
});

// --- LAP-10 ---------------------------------------------------------------------------------------------------------

it('LAP-10 aktivitas pengguna merangkum audit log dan jejak akses per pengguna dan jenis aksi', function () {
    $d = lapData();
    $r = lapLihat($d, 'pemilik', 'user-activity');

    expect(lapMetrik($r, 'Aksi tercatat'))->toBe(4)
        ->and(lapMetrik($r, 'Rekam medis dibuka'))->toBe(3)
        ->and(lapMetrik($r, 'Jenis aksi'))->toBe(3);
    $pengguna = lapTabel($r, 'Aktivitas per pengguna');
    $perNama = array_column($pengguna['rows'], 'actions', 'user');
    expect($perNama[$d['u']['pemilik']->name])->toBe(2)->and($perNama[$d['u']['dokter']->name])->toBe(1);
    $dokter = array_values(array_filter($pengguna['rows'], fn (array $row) => $row['user'] === $d['u']['dokter']->name))[0];
    expect($dokter['opened'])->toBe(3)->and($dokter['patients'])->toBe(2);
    expect(array_column(lapTabel($r, 'Aktivitas per jenis aksi')['rows'], 'count', 'action'))->toBe(['invoice.voided' => 2, 'encounter.closed' => 1, 'user.created' => 1]);
});

// --- Hak akses ------------------------------------------------------------------------------------------------------

it('LAP matriks hak akses: laporan yang boleh dibuka tiap peran', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $semua = ['daily-summary', 'revenue', 'doctor-fees', 'visits', 'procedures', 'receivables', 'stock', 'cash-closing', 'record-completeness', 'user-activity'];
    $izin = [
        'pemilik' => $semua,
        'admin' => $semua,
        'kasir' => ['daily-summary', 'receivables', 'cash-closing'],
        'dokter' => ['procedures', 'record-completeness'],
        'perawat' => ['stock'],
    ];
    foreach ($izin as $peran => $boleh) {
        foreach ($semua as $laporan) {
            $respons = test()->actingAs($d['u'][$peran])->get("/reports/{$laporan}?from=".SENIN.'&to='.SENIN);
            in_array($laporan, $boleh, true) ? $respons->assertOk() : $respons->assertForbidden();
        }
        test()->actingAs($d['u'][$peran])->get('/reports')->assertOk()->assertInertia(fn ($p) => $p->component('Reports/Index')->has('reports', count($boleh)));
    }
    test()->actingAs($d['u']['resepsionis'])->get('/reports')->assertForbidden();
    test()->actingAs($d['u']['resepsionis'])->get('/reports/stock')->assertForbidden();
});

it('LAP laporan menuntut login', function () {
    test()->get('/reports')->assertRedirect('/login');
    test()->postJson('/internal-api/reports/revenue/export', ['format' => 'pdf'])->assertUnauthorized();
    test()->getJson('/internal-api/report-jobs/01JOBJOBJOBJOBJOBJOBJOBJOB')->assertUnauthorized();
});

it('LAP laporan tidak memuat data klinik lain', function () {
    $d = lapData();
    $lain = lapData();
    tenant()->set($d['k']->id);
    $r = lapLihat($d, 'pemilik', 'daily-summary');

    expect(lapMetrik($r, 'Jumlah kunjungan'))->toBe(6)->and(lapMetrik($r, 'Penerimaan bersih'))->toBe(225000);
    expect($lain['k']->id)->not->toBe($d['k']->id);
});

it('LAP rentang tanggal tidak sah ditolak dengan pesan berbahasa Indonesia', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $terbalik = test()->actingAs($d['u']['pemilik'])->from('/reports')->get('/reports/revenue?from=2025-06-20&to=2025-06-10');
    $terbalik->assertRedirect('/reports');
    $terbalik->assertSessionHas('error', 'Tanggal akhir tidak boleh sebelum tanggal awal.');

    $terlalu = test()->actingAs($d['u']['pemilik'])->from('/reports')->get('/reports/revenue?from=2020-01-01&to=2025-06-10');
    $terlalu->assertSessionHas('error');

    test()->actingAs($d['u']['pemilik'])->get('/reports/revenue?from=bukan-tanggal')->assertSessionHasErrors('from');
    test()->actingAs($d['u']['pemilik'])->get('/reports/tidak-ada')->assertNotFound();
});

it('LAP bawaan rentang: hari ini untuk ringkasan harian dan bulan berjalan untuk lainnya', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    test()->actingAs($d['u']['pemilik'])->get('/reports/daily-summary')->assertInertia(fn ($p) => $p->where('filters.from', SELASA)->where('filters.to', SELASA));
    test()->actingAs($d['u']['pemilik'])->get('/reports/revenue')->assertInertia(fn ($p) => $p->where('filters.from', '2025-06-01')->where('filters.to', SELASA));
    test()->actingAs($d['u']['pemilik'])->get('/reports/receivables')->assertInertia(fn ($p) => $p->where('filters.from', '2024-06-18')->where('filters.to', SELASA));
});

// --- Dasbor Pemilik -------------------------------------------------------------------------------------------------

it('LAP dasbor Pemilik: pendapatan hari ini dan bulan ini dibanding bulan lalu, kunjungan, tidak datang, piutang, stok, dan grafik 30 hari', function () {
    $d = lapData();
    majuKe('2025-06-16 18:00');
    // Pembanding: tagihan bulan lalu pada rentang hari yang sama dan di luar rentang (hanya masuk total bulan lalu).
    $k = $d['k'];
    $pasien = $d['pasien']['a'];
    foreach ([['2025-05-10 10:00', 100000], ['2025-05-25 10:00', 400000]] as $i => [$terbit, $total]) {
        $enc = lapId();
        DB::table('encounters')->insert(['id' => $enc, 'clinic_id' => $k->id, 'patient_id' => $pasien, 'doctor_id' => $d['sari']->id, 'started_at' => lapUtc($terbit), 'status' => 'closed', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('invoices')->insert(['id' => lapId(), 'clinic_id' => $k->id, 'number' => 'INV-OLD-'.$i, 'patient_id' => $pasien, 'encounter_id' => $enc, 'lifecycle' => 'issued', 'status' => 'paid', 'subtotal_amount' => $total, 'discount_amount' => 0, 'total_amount' => $total, 'paid_amount' => $total, 'tax_amount' => 0, 'tax_basis_points' => 0, 'issued_at' => lapUtc($terbit), 'created_at' => now(), 'updated_at' => now()]);
    }

    tenant()->set($k->id);
    test()->actingAs($d['u']['pemilik'])->get('/dashboard')->assertOk()->assertInertia(function ($p) {
        $p->component('Beranda')
            ->where('dashboard.date', SENIN)
            ->where('dashboard.revenue_today', 350000)
            ->where('dashboard.revenue_month', 350000)
            ->where('dashboard.revenue_prev_month', 500000)
            ->where('dashboard.revenue_prev_month_same_period', 100000)
            ->where('dashboard.revenue_change_basis_points', 25000)
            ->where('dashboard.visits_today', 6)
            ->where('dashboard.visits_month', 6)
            ->where('dashboard.no_show_count', 1)
            ->where('dashboard.no_show_basis_points', 5000)
            ->where('dashboard.receivables_total', 150000)
            ->where('dashboard.receivables_count', 1)
            ->where('dashboard.low_stock_count', 1)
            ->has('dashboard.series', 30)
            ->where('dashboard.series.29', ['date' => SENIN, 'revenue' => 350000])
            ->where('dashboard.series.0.date', '2025-05-18');
    });

    tenant()->set($k->id);
    test()->actingAs($d['u']['kasir'])->get('/dashboard')->assertOk()->assertInertia(fn ($p) => $p->where('dashboard', null));
    test()->actingAs($d['u']['dokter'])->get('/dashboard')->assertOk()->assertInertia(fn ($p) => $p->where('dashboard', null));
});

// --- Ekspor lewat antrean ------------------------------------------------------------------------------------------

it('LAP ekspor XLSX dan PDF lewat antrean: status selesai dan berkas dapat diunduh pemintanya', function () {
    $d = lapData();
    tenant()->set($d['k']->id);

    foreach (['xlsx' => 'PK', 'pdf' => '%PDF'] as $format => $awal) {
        tenant()->set($d['k']->id);
        $minta = test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/revenue/export', ['format' => $format, 'from' => SENIN, 'to' => SENIN]);
        $minta->assertStatus(202)->assertJsonPath('data.format', $format)->assertJsonPath('data.report_key', 'revenue');
        $id = $minta->json('data.id');

        tenant()->set($d['k']->id);
        $status = test()->actingAs($d['u']['pemilik'])->getJson("/internal-api/report-jobs/{$id}");
        $status->assertOk()->assertJsonPath('data.status', 'done')->assertJsonPath('data.status_label', 'Selesai')->assertJsonPath('data.download_url', "/report-jobs/{$id}/download");

        tenant()->set($d['k']->id);
        $unduh = test()->actingAs($d['u']['pemilik'])->get("/report-jobs/{$id}/download");
        $unduh->assertOk()->assertHeader('Content-Type', ExportFormat::from($format)->mimeType());
        expect(str_starts_with($unduh->getContent(), $awal))->toBeTrue()
            ->and((string) $unduh->headers->get('Content-Disposition'))->toContain('lap-02-revenue_'.SENIN.'_'.SENIN.".{$format}");
        expect(Storage::disk('private')->allFiles('reports'))->not->toBe([]);
    }
});

it('LAP berkas ekspor terenkripsi di disk dan isi XLSX memuat angka laporan', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $id = test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/daily-summary/export', ['format' => 'xlsx', 'from' => SENIN, 'to' => SENIN])->json('data.id');
    tenant()->set($d['k']->id);
    $isi = test()->actingAs($d['u']['pemilik'])->get("/report-jobs/{$id}/download")->getContent();

    $berkas = Storage::disk('private')->allFiles('reports')[0];
    expect(Storage::disk('private')->get($berkas))->not->toStartWith('PK')->and(str_ends_with($berkas, '.xlsx.enc'))->toBeTrue();

    $tmp = tempnam(sys_get_temp_dir(), 'lap');
    file_put_contents($tmp, $isi);
    $buku = IOFactory::load($tmp);
    unlink($tmp);
    expect($buku->getSheetNames())->toContain('1 Ringkasan', '2 Penerimaan per metode bayar');
    $ringkasan = $buku->getSheetByName('1 Ringkasan');
    $nilai = [];
    foreach ($ringkasan?->toArray(null, true, false) ?? [] as $baris) {
        $nilai[(string) $baris[0]] = $baris[1] ?? null;
    }
    expect($nilai['Penerimaan bersih'])->toBe(225000)->and($nilai['Periode'])->toBe('16/06/2025 - 16/06/2025');
    $metode = $buku->getSheetByName('2 Penerimaan per metode bayar')?->toArray(null, true, false) ?? [];
    $judul = array_search('Metode bayar', array_column($metode, 0), true);
    expect($judul)->not->toBeFalse();
    expect(array_column(array_slice($metode, (int) $judul + 1, 2), 5, 0))->toBe(['QRIS' => 50000, 'Tunai' => 175000]);
});

it('LAP berkas ekspor hanya dapat dilihat dan diunduh pemintanya', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $id = test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/revenue/export', ['format' => 'pdf', 'from' => SENIN, 'to' => SENIN])->json('data.id');

    tenant()->set($d['k']->id);
    test()->actingAs($d['u']['admin'])->getJson("/internal-api/report-jobs/{$id}")->assertNotFound()->assertJsonPath('code', 'report.job_not_found');
    test()->actingAs($d['u']['admin'])->get("/report-jobs/{$id}/download")->assertNotFound();

    $lain = lapData();
    tenant()->set($lain['k']->id);
    test()->actingAs($lain['u']['pemilik'])->getJson("/internal-api/report-jobs/{$id}")->assertNotFound();
});

it('LAP ekspor mengikuti hak akses laporan dan format yang sah', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    test()->actingAs($d['u']['kasir'])->postJson('/internal-api/reports/revenue/export', ['format' => 'pdf'])->assertForbidden();
    test()->actingAs($d['u']['resepsionis'])->postJson('/internal-api/reports/stock/export', ['format' => 'pdf'])->assertForbidden();
    test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/revenue/export', ['format' => 'docx'])->assertStatus(422)->assertJsonValidationErrors('format');
    test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/revenue/export', [])->assertStatus(422)->assertJsonValidationErrors('format');
    test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/revenue/export', ['format' => 'pdf', 'from' => '2025-06-30', 'to' => '2025-06-01'])->assertStatus(422)->assertJsonPath('code', 'report.range_invalid');
    test()->actingAs($d['u']['kasir'])->postJson('/internal-api/reports/cash-closing/export', ['format' => 'xlsx', 'from' => SENIN, 'to' => SENIN])->assertStatus(202);
});

it('LAP ekspor kasir tetap dibatasi ke kas miliknya meski diproses antrean', function () {
    $d = lapData();
    $lain = makeUser(Role::Kasir, $d['k']);
    DB::table('cash_closings')->insert(['id' => lapId(), 'clinic_id' => $d['k']->id, 'closing_date' => SENIN, 'cashier_id' => $lain->id, 'system_amount' => 99000, 'counted_amount' => 99000, 'difference_amount' => 0, 'closed_at' => now(), 'created_at' => now()]);
    tenant()->set($d['k']->id);

    $id = test()->actingAs($d['u']['kasir'])->postJson('/internal-api/reports/cash-closing/export', ['format' => 'xlsx', 'from' => SENIN, 'to' => SENIN])->json('data.id');
    tenant()->set($d['k']->id);
    $isi = test()->actingAs($d['u']['kasir'])->get("/report-jobs/{$id}/download")->getContent();
    $tmp = tempnam(sys_get_temp_dir(), 'lap');
    file_put_contents($tmp, $isi);
    $buku = IOFactory::load($tmp);
    unlink($tmp);
    $ringkasan = $buku->getSheetByName('1 Ringkasan')?->toArray(null, true, false) ?? [];
    $nilai = [];
    foreach ($ringkasan as $baris) {
        $nilai[(string) $baris[0]] = $baris[1] ?? null;
    }
    expect($nilai['Penutupan kas'])->toBe(1);
});

it('LAP job ekspor idempoten: dijalankan dua kali tidak menggandakan berkas', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $id = test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/visits/export', ['format' => 'pdf', 'from' => SENIN, 'to' => SENIN])->json('data.id');
    $sebelum = Storage::disk('private')->allFiles('reports');

    tenant()->set($d['k']->id);
    app(ReportExportService::class)->process($id);
    app(ReportExportService::class)->process($id);

    expect(Storage::disk('private')->allFiles('reports'))->toBe($sebelum)
        ->and(DB::table('report_jobs')->where('id', $id)->value('status'))->toBe('done');
});

it('LAP ekspor yang gagal ditandai gagal dengan pesan aman tanpa memutus permintaan', function () {
    $d = lapData();
    app()->bind(ReportRenderer::class, fn () => new class implements ReportRenderer
    {
        public function render(ExportFormat $format, ReportResult $result): string
        {
            throw new RuntimeException('detail internal rahasia');
        }
    });
    tenant()->set($d['k']->id);

    $minta = test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/revenue/export', ['format' => 'pdf', 'from' => SENIN, 'to' => SENIN]);
    $minta->assertStatus(202)->assertJsonPath('data.status', 'failed');
    expect(json_encode($minta->json()))->not->toContain('rahasia');
    tenant()->set($d['k']->id);
    $id = $minta->json('data.id');
    test()->actingAs($d['u']['pemilik'])->getJson("/report-jobs/{$id}/download")->assertStatus(409)->assertJsonPath('code', 'report.job_not_ready');
});

it('LAP berkas ekspor kedaluwarsa dilepas oleh perintah pemangkasan', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $id = test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/revenue/export', ['format' => 'pdf', 'from' => SENIN, 'to' => SENIN])->json('data.id');
    expect(Storage::disk('private')->allFiles('reports'))->toHaveCount(1);

    majuKe('2025-06-30 08:00:00');
    test()->artisan('sikg:reports:prune')->assertSuccessful();
    expect(Storage::disk('private')->allFiles('reports'))->toHaveCount(0);

    tenant()->set($d['k']->id);
    test()->actingAs($d['u']['pemilik'])->getJson("/report-jobs/{$id}/download")->assertStatus(409)->assertJsonPath('code', 'report.job_expired');
    test()->actingAs($d['u']['pemilik'])->getJson("/internal-api/report-jobs/{$id}")->assertOk()->assertJsonPath('data.status', 'done')->assertJsonPath('data.download_url', null);
});

it('LAP berkas yang menggantung berjalan terlalu lama dinyatakan gagal agar layar tidak menunggu selamanya', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $id = lapId();
    DB::table('report_jobs')->insert(['id' => $id, 'clinic_id' => $d['k']->id, 'report_key' => 'revenue', 'format' => 'pdf', 'status' => 'running', 'scope' => 'full', 'filters' => '{}', 'requested_by' => $d['u']['pemilik']->id, 'started_at' => now()->subMinutes(30), 'created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(30)]);

    test()->actingAs($d['u']['pemilik'])->getJson("/internal-api/report-jobs/{$id}")->assertOk()->assertJsonPath('data.status', 'failed');
});

it('LAP ekspor dan unduhan tercatat di audit log', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $id = test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/revenue/export', ['format' => 'pdf', 'from' => SENIN, 'to' => SENIN])->json('data.id');
    tenant()->set($d['k']->id);
    test()->actingAs($d['u']['pemilik'])->get("/report-jobs/{$id}/download")->assertOk();

    $aksi = DB::table('audit_logs')->where('subject_type', 'report_job')->where('subject_id', $id)->orderBy('created_at')->pluck('action')->all();
    expect($aksi)->toContain('report.export_requested')->toContain('report.downloaded');
});

// --- Kinerja --------------------------------------------------------------------------------------------------------

it('NFR-01 laporan harian dimuat kurang dari 3 detik pada data satu tahun', function () {
    $d = lapData();
    $k = $d['k'];
    $now = now();
    $encounters = [];
    $invoices = [];
    $payments = [];
    for ($i = 0; $i < 1500; $i++) {
        $enc = lapId();
        $waktu = lapUtc('2025-06-16 08:00');
        $encounters[] = ['id' => $enc, 'clinic_id' => $k->id, 'patient_id' => $d['pasien']['a'], 'doctor_id' => $d['sari']->id, 'started_at' => $waktu, 'status' => 'closed', 'version' => 1, 'created_at' => $now, 'updated_at' => $now];
        $inv = lapId();
        $invoices[] = ['id' => $inv, 'clinic_id' => $k->id, 'number' => 'INV-PERF-'.$i, 'patient_id' => $d['pasien']['a'], 'encounter_id' => $enc, 'lifecycle' => 'issued', 'status' => 'paid', 'subtotal_amount' => 100000, 'discount_amount' => 0, 'total_amount' => 100000, 'paid_amount' => 100000, 'tax_amount' => 0, 'tax_basis_points' => 0, 'issued_at' => $waktu, 'created_at' => $now, 'updated_at' => $now];
        $payments[] = ['id' => lapId(), 'clinic_id' => $k->id, 'invoice_id' => $inv, 'payment_method_id' => $d['tunai'], 'type' => 'pay', 'amount' => 100000, 'paid_at' => $waktu, 'received_by' => $d['u']['kasir']->id, 'idempotency_key' => 'perf-'.$i, 'receipt_no' => 'KW-PERF-'.$i, 'created_at' => $now];
    }
    foreach (array_chunk($encounters, 500) as $potong) {
        DB::table('encounters')->insert($potong);
    }
    foreach (array_chunk($invoices, 500) as $potong) {
        DB::table('invoices')->insert($potong);
    }
    foreach (array_chunk($payments, 500) as $potong) {
        DB::table('payments')->insert($potong);
    }

    tenant()->set($k->id);
    $mulai = microtime(true);
    $r = lapLihat($d, 'pemilik', 'daily-summary');
    $detik = microtime(true) - $mulai;

    expect(lapMetrik($r, 'Tagihan terbit'))->toBe(1502)->and($detik)->toBeLessThan(3.0);
});

it('LAP ekspor XLSX menulis teks berawalan = sebagai string, bukan rumus (formula injection)', function () {
    $d = lapData();
    DB::table('patients')->where('id', $d['pasien']['b'])->update(['name' => '=1+1', 'name_search' => '=1+1']);
    tenant()->set($d['k']->id);

    $id = test()->actingAs($d['u']['admin'])->postJson('/internal-api/reports/record-completeness/export', ['format' => 'xlsx', 'from' => SENIN, 'to' => SENIN])->json('data.id');
    tenant()->set($d['k']->id);
    $isi = test()->actingAs($d['u']['admin'])->get("/report-jobs/{$id}/download")->getContent();
    $tmp = tempnam(sys_get_temp_dir(), 'lap');
    file_put_contents($tmp, $isi);
    $buku = IOFactory::load($tmp);
    unlink($tmp);

    $lembar = $buku->getSheet(2);
    $ditemukan = false;
    foreach ($lembar?->getRowIterator() ?? [] as $baris) {
        foreach ($baris->getCellIterator() as $sel) {
            if ($sel->getValue() === '=1+1') {
                $ditemukan = true;
                expect($sel->getDataType())->toBe(DataType::TYPE_STRING);
            }
        }
    }
    expect($ditemukan)->toBeTrue();
});

it('LAP berkas yang sudah dibuat tidak dapat diunduh lagi setelah izin laporan pemintanya dicabut', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $id = test()->actingAs($d['u']['kasir'])->postJson('/internal-api/reports/receivables/export', ['format' => 'pdf', 'from' => SENIN, 'to' => SENIN])->json('data.id');

    tenant()->set($d['k']->id);
    test()->actingAs($d['u']['kasir'])->get("/report-jobs/{$id}/download")->assertOk();

    $d['u']['kasir']->revokePermissionTo('report.financial.own');
    $d['u']['kasir']->syncRoles([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    tenant()->set($d['k']->id);
    test()->actingAs($d['u']['kasir']->fresh())->getJson("/report-jobs/{$id}/download")->assertForbidden();
});

it('LAP job yang sudah selesai tidak diambil ulang oleh pekerja yang terlambat', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $id = test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/visits/export', ['format' => 'pdf', 'from' => SENIN, 'to' => SENIN])->json('data.id');
    tenant()->set($d['k']->id);

    $store = app(ReportJobStore::class);
    expect($store->claim($id, now()->toDateTimeImmutable()))->toBeNull();
    $store->fail($id, 'terlambat', now()->toDateTimeImmutable());
    expect(DB::table('report_jobs')->where('id', $id)->value('status'))->toBe('done');
});

it('LAP-06 dan LAP-09 yang menampilkan nama pasien mencatat pembukaannya di audit log, laporan agregat tidak', function () {
    $d = lapData();
    lapLihat($d, 'admin', 'record-completeness');
    lapLihat($d, 'pemilik', 'receivables');
    lapLihat($d, 'pemilik', 'revenue');

    $catatan = DB::table('audit_logs')->where('action', 'report.opened')->orderBy('subject_id')->get(['actor_id', 'subject_id', 'after']);
    expect($catatan->pluck('subject_id')->all())->toBe(['LAP-06', 'LAP-09'])
        ->and($catatan[1]->actor_id)->toBe($d['u']['admin']->id)
        ->and((string) $catatan[1]->after)->not->toContain('Pasien');
});

it('LAP berkas XLSX tampil profesional: judul, tajuk dibekukan dengan filter, format Rupiah, nol tetap tertulis, dan siap cetak', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $id = test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/revenue/export', ['format' => 'xlsx', 'from' => SENIN, 'to' => SENIN])->json('data.id');
    tenant()->set($d['k']->id);
    $isi = test()->actingAs($d['u']['pemilik'])->get("/report-jobs/{$id}/download")->getContent();
    $tmp = tempnam(sys_get_temp_dir(), 'lap');
    file_put_contents($tmp, $isi);
    $buku = IOFactory::load($tmp);
    unlink($tmp);

    expect($buku->getProperties()->getCreator())->toBe('Klinik Uji')->and($buku->getProperties()->getTitle())->toBe('LAP-02 Pendapatan')
        ->and($buku->getProperties()->getSubject())->toContain('Periode 16/06/2025');

    $ringkasan = $buku->getSheetByName('1 Ringkasan');
    expect($ringkasan?->getCell('A1')->getValue())->toBe('KLINIK UJI')->and($ringkasan?->getCell('A3')->getValue())->toBe('LAP-02 Pendapatan')
        ->and($ringkasan?->getCell('A7')->getValue())->toBe('Keterangan')->and($ringkasan?->getCell('A7')->getStyle()->getFont()->getBold())->toBeTrue()
        ->and($ringkasan?->getCell('B10')->getStyle()->getNumberFormat()->getFormatCode())->toContain('"Rp"')
        ->and($ringkasan?->getCell('B14')->getValue())->toBe(0)
        ->and($ringkasan?->getShowGridlines())->toBeFalse();

    $tabel = $buku->getSheetByName('2 Pendapatan per hari');
    expect($tabel)->not->toBeNull()
        ->and($tabel?->getFreezePane())->toBe('A8')
        ->and($tabel?->getAutoFilter()->getRange())->toStartWith('A7:')
        ->and($tabel?->getCell('A4')->getValue())->toBe('Pendapatan per hari')
        ->and($tabel?->getCell('A7')->getStyle()->getFont()->getBold())->toBeTrue()
        ->and($tabel?->getPageSetup()->getFitToWidth())->toBe(1)->and($tabel?->getPageSetup()->getFitToHeight())->toBe(0)
        ->and($tabel?->getPageSetup()->getRowsToRepeatAtTop())->toBe([7, 7])
        ->and($tabel?->getPageSetup()->getPaperSize())->toBe(PageSetup::PAPERSIZE_A4)
        ->and($tabel?->getPageSetup()->getOrientation())->toBe(PageSetup::ORIENTATION_LANDSCAPE)
        ->and($tabel?->getHeaderFooter()->getOddFooter())->toContain('Halaman &P dari &N');
    // Kolom Pajak bernilai 0 harus tertulis 0, bukan sel kosong.
    expect($tabel?->getCell('E8')->getValue())->toBe(0);
});

it('ADR-0008 kop PDF laporan sama dengan kop semua cetakan klinik', function () {
    $d = lapData();
    tenant()->set($d['k']->id);
    $kop = isiKop($d['k']);
    rekamPdf();

    test()->actingAs($d['u']['pemilik'])->postJson('/internal-api/reports/revenue/export', ['format' => 'pdf', 'from' => SENIN, 'to' => SENIN])->assertStatus(202);

    expect(PdfPerekam::$permintaan)->not->toBeEmpty()->and(PdfPerekam::$permintaan[0]->data['letterhead'])->toBe($kop);
});
