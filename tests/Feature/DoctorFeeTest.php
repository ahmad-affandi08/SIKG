<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Clinic\Domain\FeeMode;
use Src\Clinic\Domain\FeeRule;
use Src\Clinic\Domain\FeeScheme;
use Src\Clinic\Domain\FeeTier;
use Src\Clinic\Domain\InvalidDoctorData;
use Src\Clinic\Infrastructure\Eloquent\DoctorModel;
use Src\IdentityAccess\Domain\Role;
use Src\Lab\Infrastructure\Eloquent\LaboratoryModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-30 18:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function idJasa(): string
{
    return strtoupper((string) Str::ulid());
}

/** Waktu lokal klinik ke UTC untuk disimpan. */
function utcJasa(string $local): string
{
    return Carbon::parse($local, 'Asia/Jakarta')->utc()->format('Y-m-d H:i:s');
}

/**
 * Satu dokter bersekema bertingkat (30% sampai Rp 1.000.000, 40% di atasnya) dengan beban lab 50%, dan data dua bulan:
 * - bulan lalu: CABUT Rp 600.000 + TAMBAL Rp 700.000 (produksi 1.300.000), pesanan lab diterima biaya Rp 200.000;
 * - bulan ini: CABUT Rp 500.000 (produksi 500.000), pesanan lab diterima biaya Rp 400.000.
 *
 * @return array<string, mixed>
 */
function siapJasa(?array $skema = null): array
{
    $k = makeClinic();
    $s = ['k' => $k, 'pemilik' => makeUser(Role::Pemilik, $k), 'admin' => makeUser(Role::Admin, $k), 'kasir' => makeUser(Role::Kasir, $k), 'dokter_user' => makeUser(Role::Dokter, $k)];
    tenant()->set($k->id);
    $dokter = makeDoctor($k, 'drg. Sari');
    DB::table('doctors')->where('id', $dokter->id)->update(['sip_no' => 'SIP-77/2025', 'fee_scheme' => json_encode($skema ?? [
        'default' => ['mode' => 'percentage', 'value' => 1000], 'overrides' => [],
        'tiers' => [['up_to' => 1000000, 'rate_bp' => 3000], ['up_to' => null, 'rate_bp' => 4000]], 'lab_share_bp' => 5000,
    ])]);
    $s['dokter'] = $dokter;
    $pasien = makePatient($k, 'Pasien Jasa');
    $now = now();

    $tagihan = function (string $terbit, array $baris) use ($k, $dokter, $pasien, $now, $s): void {
        $kunjungan = idJasa();
        DB::table('encounters')->insert(['id' => $kunjungan, 'clinic_id' => $k->id, 'patient_id' => $pasien, 'doctor_id' => $dokter->id, 'started_at' => utcJasa($terbit), 'status' => 'closed', 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $total = array_sum(array_column($baris, 2));
        $invoice = idJasa();
        DB::table('invoices')->insert([
            'id' => $invoice, 'clinic_id' => $k->id, 'number' => 'INV-'.substr($invoice, -6), 'patient_id' => $pasien, 'encounter_id' => $kunjungan, 'lifecycle' => 'issued', 'status' => 'paid',
            'subtotal_amount' => $total, 'discount_amount' => 0, 'total_amount' => $total, 'paid_amount' => $total, 'tax_amount' => 0, 'tax_basis_points' => 0,
            'issued_at' => utcJasa($terbit), 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ($baris as [$kode, $jumlah, $nilai]) {
            $prosedur = idJasa();
            DB::table('procedures')->insertOrIgnore(['id' => $prosedur, 'clinic_id' => $k->id, 'code' => $kode, 'name' => 'Tindakan '.$kode, 'scope' => 'tooth', 'default_price_amount' => $nilai, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            $entri = idJasa();
            DB::table('encounter_procedures')->insert([
                'id' => $entri, 'clinic_id' => $k->id, 'encounter_id' => $kunjungan, 'procedure_id' => DB::table('procedures')->where('code', $kode)->value('id'), 'procedure_code' => $kode, 'procedure_name' => 'Tindakan '.$kode, 'scope' => 'tooth',
                'quantity' => $jumlah, 'unit_price_amount' => intdiv($nilai, $jumlah), 'performed_by' => $s['dokter_user']->id, 'performed_at' => utcJasa($terbit), 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('invoice_items')->insert(['id' => idJasa(), 'clinic_id' => $k->id, 'invoice_id' => $invoice, 'source_type' => 'procedure', 'source_id' => $entri, 'description' => $kode, 'quantity' => $jumlah, 'unit_price_amount' => intdiv($nilai, $jumlah), 'discount_amount' => 0, 'total_amount' => $nilai, 'created_at' => $now, 'updated_at' => $now]);
        }
    };
    $tagihan('2025-05-12 10:00', [['CABUT', 1, 600000], ['TAMBAL', 2, 700000]]);
    $tagihan('2025-06-10 10:00', [['CABUT', 1, 500000]]);

    $lab = new LaboratoryModel;
    $lab->forceFill(['name' => 'Lab Jasa', 'is_active' => true])->save();
    foreach ([['2025-05-20', 200000, 'received'], ['2025-06-15', 400000, 'received'], ['2025-06-16', 999000, 'sent']] as $i => [$terima, $biaya, $status]) {
        DB::table('lab_orders')->insert([
            'id' => idJasa(), 'clinic_id' => $k->id, 'order_no' => 'LAB-2025-0000'.($i + 1), 'patient_id' => $pasien, 'doctor_id' => $dokter->id, 'laboratory_id' => $lab->id, 'work_type' => 'crown',
            'tooth_notes' => '', 'description' => 'Mahkota', 'status' => $status, 'ordered_date' => '2025-05-01', 'received_date' => $status === 'received' ? $terima : null, 'lab_cost_amount' => $biaya,
            'patient_charge_amount' => 0, 'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    return $s;
}

/**
 * @param  array<string, mixed>  $s
 * @return array<string, mixed>
 */
function lihatJasa(array $s, string $peran = 'pemilik', string $query = 'from=2025-05-01&to=2025-06-30'): array
{
    tenant()->set($s['k']->id);
    $hasil = [];
    test()->actingAs($s[$peran])->get('/reports/doctor-fees?'.$query)->assertOk()->assertInertia(function ($p) use (&$hasil) {
        $hasil = $p->toArray()['props'];

        return $p;
    });

    return $hasil['report'];
}

/**
 * @param  array<string, mixed>  $laporan
 * @return array<string, mixed>
 */
function tabelJasa(array $laporan, string $judul): array
{
    foreach ($laporan['tables'] as $t) {
        if ($t['title'] === $judul) {
            return $t;
        }
    }
    throw new RuntimeException("Tabel '{$judul}' tidak ada");
}

it('P2-09 skema dokter menyimpan jasa bertingkat dan beban lab, skema lama tidak berubah bentuk', function () {
    $lama = new FeeScheme(new FeeRule(FeeMode::Percentage, 3000), ['CABUT' => new FeeRule(FeeMode::Fixed, 75000)]);
    $baru = new FeeScheme(new FeeRule(FeeMode::Percentage, 3000), [], [new FeeTier(1000000, 3000), new FeeTier(null, 4000)], 5000);

    expect(array_keys($lama->toArray()))->toBe(['default', 'overrides'])
        ->and($baru->toArray()['tiers'])->toBe([['up_to' => 1000000, 'rate_bp' => 3000], ['up_to' => null, 'rate_bp' => 4000]])->and($baru->toArray()['lab_share_bp'])->toBe(5000)
        ->and(FeeScheme::fromArray($baru->toArray())->toArray())->toBe($baru->toArray());
});

it('P2-09 skema menolak tingkat yang tidak menaik, tanpa tingkat terakhir tak terbatas, atau beban lab di atas 100 persen', function () {
    expect(fn () => new FeeScheme(new FeeRule(FeeMode::Percentage, 0), [], [new FeeTier(5000, 3000), new FeeTier(2000, 3500), new FeeTier(null, 4000)]))->toThrow(InvalidDoctorData::class, 'makin besar')
        ->and(fn () => new FeeScheme(new FeeRule(FeeMode::Percentage, 0), [], [new FeeTier(5000, 3000), new FeeTier(8000, 3500)]))->toThrow(InvalidDoctorData::class, 'tanpa batas')
        ->and(fn () => new FeeScheme(new FeeRule(FeeMode::Percentage, 0), [], [new FeeTier(null, 3000), new FeeTier(null, 4000)]))->toThrow(InvalidDoctorData::class)
        ->and(fn () => new FeeTier(1000, 10001))->toThrow(InvalidDoctorData::class)
        ->and(fn () => new FeeScheme(new FeeRule(FeeMode::Percentage, 0), [], [], 10001))->toThrow(InvalidDoctorData::class, 'Beban biaya lab');
});

it('P2-09 Admin menyimpan skema bertingkat dan beban lab lewat formulir dokter', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->post('/settings/doctors', [
        'name' => 'drg. Bertingkat', 'fee_mode' => 'percentage', 'fee_value' => '10', 'fee_overrides' => [],
        'fee_tiers' => [['up_to' => '10000000', 'rate' => '30'], ['up_to' => '', 'rate' => '32.5']], 'fee_lab_share' => '50', 'schedule' => [],
    ])->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    $skema = DoctorModel::query()->firstOrFail()->fee_scheme;
    expect($skema['tiers'])->toBe([['up_to' => 10000000, 'rate_bp' => 3000], ['up_to' => null, 'rate_bp' => 3250]])->and($skema['lab_share_bp'])->toBe(5000);

    $this->actingAs($admin)->post('/settings/doctors', [
        'name' => 'drg. Salah', 'fee_mode' => 'percentage', 'fee_value' => '10', 'fee_overrides' => [],
        'fee_tiers' => [['up_to' => '5000000', 'rate' => '30'], ['up_to' => '1000000', 'rate' => '35'], ['up_to' => '', 'rate' => '40']], 'schedule' => [],
    ])->assertSessionHasErrors('domain');
    $this->actingAs($admin)->post('/settings/doctors', [
        'name' => 'drg. Salah', 'fee_mode' => 'percentage', 'fee_value' => '10', 'fee_overrides' => [], 'fee_tiers' => [['up_to' => '10.000', 'rate' => '30']], 'schedule' => [],
    ])->assertSessionHasErrors('fee_tiers.0.up_to');
    expect(DoctorModel::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('P2-09 laporan jasa menghitung bertingkat per bulan dengan potongan biaya lab dan jasa bersih', function () {
    $s = siapJasa();

    $r = lihatJasa($s);

    // Bulan lalu: produksi 1.300.000 = 1.000.000 x 30% + 300.000 x 40% = 420.000; lab 200.000 x 50% = 100.000 -> bersih 320.000.
    // Bulan ini: produksi 500.000 x 30% = 150.000; lab 400.000 x 50% = 200.000 -> bersih 0, tak tertutup 50.000 (pesanan belum diterima tidak dihitung).
    $bulan = collect(tabelJasa($r, 'Rekap per bulan')['rows'])->keyBy('month');
    expect($bulan['2025-05'])->toMatchArray(['base' => 1300000, 'gross' => 420000, 'lab_cost' => 200000, 'lab' => 100000, 'fee' => 320000, 'uncovered' => 0])
        ->and($bulan['2025-06'])->toMatchArray(['base' => 500000, 'gross' => 150000, 'lab_cost' => 400000, 'lab' => 200000, 'fee' => 0, 'uncovered' => 50000]);

    $metrik = array_column($r['metrics'], 'value', 'label');
    expect($metrik['Jasa kotor'])->toBe(570000)->and($metrik['Potongan biaya lab'])->toBe(300000)->and($metrik['Total jasa dokter'])->toBe(320000)->and($metrik['Nilai tindakan (dasar hitung)'])->toBe(1800000);
    $dokter = tabelJasa($r, 'Jasa per dokter');
    expect($dokter['rows'][0])->toMatchArray(['doctor' => 'drg. Sari', 'gross' => 570000, 'lab' => 300000, 'fee' => 320000])
        ->and($dokter['totals']['fee'])->toBe(320000);
    $rincian = collect(tabelJasa($r, 'Rincian per tindakan')['rows'])->keyBy('code');
    expect($rincian['CABUT']['qty'])->toBe(2)->and($rincian['CABUT']['rule'])->toBe('Bertingkat')
        ->and($rincian['CABUT']['fee'] + $rincian['TAMBAL']['fee'])->toBe(570000);
    expect(implode(' ', $r['notes']))->toContain('Skema drg. Sari: Bertingkat per bulan')->toContain('beban biaya lab 50%');
});

it('P2-09 rentang satu bulan hanya memuat bulan itu dan lab diterima di luar rentang tidak dipotong', function () {
    $s = siapJasa();

    $r = lihatJasa($s, 'admin', 'from=2025-05-01&to=2025-05-31');

    $metrik = array_column($r['metrics'], 'value', 'label');
    expect($metrik['Total jasa dokter'])->toBe(320000)->and($metrik['Potongan biaya lab'])->toBe(100000)->and(tabelJasa($r, 'Rekap per bulan')['rows'])->toHaveCount(1);
});

it('P2-09 bulan tanpa tindakan tetap memuat potongan lab yang tak tertutup', function () {
    $s = siapJasa();
    DB::table('invoices')->where('issued_at', '>=', '2025-06-01')->update(['lifecycle' => 'void', 'status' => 'void', 'voided_at' => now(), 'void_reason' => 'Batal']);

    $r = lihatJasa($s, 'pemilik', 'from=2025-06-01&to=2025-06-30');

    $bulan = tabelJasa($r, 'Rekap per bulan')['rows'][0];
    expect($bulan)->toMatchArray(['month' => '2025-06', 'base' => 0, 'gross' => 0, 'lab' => 200000, 'fee' => 0, 'uncovered' => 200000]);
});

it('P2-09 slip jasa dokter berupa PDF dari perhitungan yang sama dan tercatat di audit', function () {
    $s = siapJasa();
    $query = http_build_query(['doctor_id' => $s['dokter']->id, 'from' => '2025-05-01', 'to' => '2025-06-30']);

    $respons = $this->actingAs($s['pemilik'])->get('/reports/doctor-fees/slip?'.$query)->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect(str_starts_with((string) $respons->getContent(), '%PDF'))->toBeTrue()
        ->and(DB::table('audit_logs')->where('action', 'report.fee_slip_printed')->count())->toBe(1);
    $audit = DB::table('audit_logs')->where('action', 'report.fee_slip_printed')->first();
    expect($audit->subject_id)->toBe($s['dokter']->id)->and((string) $audit->after)->toContain('320000');
});

it('P2-09 slip menolak dokter tanpa data, rentang terbalik, dan masukan salah', function () {
    $s = siapJasa();
    $kosong = makeDoctor($s['k'], 'drg. Kosong');
    tenant()->set($s['k']->id);

    $this->actingAs($s['pemilik'])->get('/reports/doctor-fees/slip?'.http_build_query(['doctor_id' => $kosong->id, 'from' => '2025-05-01', 'to' => '2025-06-30']))->assertSessionHas('error_code', 'report.no_data');
    $this->get('/reports/doctor-fees/slip?'.http_build_query(['doctor_id' => $s['dokter']->id, 'from' => '2025-06-30', 'to' => '2025-06-01']))->assertSessionHas('error_code', 'report.range_invalid');
    $this->get('/reports/doctor-fees/slip?'.http_build_query(['doctor_id' => strtoupper((string) Str::ulid()), 'from' => '2025-05-01', 'to' => '2025-06-30']))->assertNotFound();
    $this->get('/reports/doctor-fees/slip?doctor_id=x&from=bukan&to=tanggal')->assertSessionHasErrors(['doctor_id', 'from', 'to']);
    expect(DB::table('audit_logs')->where('action', 'report.fee_slip_printed')->count())->toBe(0);
});

it('P2-09 slip hanya untuk pemegang laporan keuangan penuh', function () {
    $s = siapJasa();
    $query = http_build_query(['doctor_id' => $s['dokter']->id, 'from' => '2025-05-01', 'to' => '2025-06-30']);

    foreach (['kasir', 'dokter_user'] as $peran) {
        $this->actingAs($s[$peran])->get('/reports/doctor-fees/slip?'.$query)->assertForbidden();
    }
    $this->actingAs($s['admin'])->get('/reports/doctor-fees/slip?'.$query)->assertOk();
});

it('P2-09 laporan jasa tanpa tingkat dan lab tetap seperti skema lama (tanpa potongan)', function () {
    $s = siapJasa(['default' => ['mode' => 'percentage', 'value' => 4000], 'overrides' => ['TAMBAL' => ['mode' => 'fixed', 'value' => 50000]]]);

    $r = lihatJasa($s);

    // CABUT 40% x (600.000 + 500.000) = 440.000; TAMBAL 2 x 50.000 = 100.000.
    $metrik = array_column($r['metrics'], 'value', 'label');
    expect($metrik['Total jasa dokter'])->toBe(540000)->and($metrik['Potongan biaya lab'])->toBe(0)->and($metrik['Jasa kotor'])->toBe(540000);
});
