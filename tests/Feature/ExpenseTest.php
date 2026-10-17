<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Billing\Infrastructure\Eloquent\ExpenseCategoryModel;
use Src\IdentityAccess\Domain\Role;
use Src\Lab\Infrastructure\Eloquent\LaboratoryModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-17 08:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Klinik dengan Pemilik, Admin, Kasir, dan dua kategori biaya.
 *
 * @return array<string, mixed>
 */
function siapBiaya(): array
{
    $k = makeClinic();
    $s = ['k' => $k, 'pemilik' => makeUser(Role::Pemilik, $k), 'admin' => makeUser(Role::Admin, $k), 'kasir' => makeUser(Role::Kasir, $k), 'dokter' => makeUser(Role::Dokter, $k)];
    tenant()->set($k->id);
    foreach (['listrik' => 'Listrik dan air', 'sewa' => 'Sewa tempat'] as $kunci => $nama) {
        $kategori = new ExpenseCategoryModel;
        $kategori->forceFill(['name' => $nama, 'is_active' => true])->save();
        $s[$kunci] = $kategori;
    }

    return $s;
}

/** @param array<string, mixed> $o */
function catatBiaya(array $s, array $o = [], string $peran = 'admin')
{
    return test()->actingAs($s[$peran])->post('/expenses', [
        'expense_date' => '2025-06-10', 'category_id' => $s['listrik']->id, 'amount' => 1500000, 'payee' => 'PLN', 'description' => 'Listrik Mei', 'reference_no' => 'KW-001', ...$o,
    ]);
}

it('P2-08 admin mencatat biaya operasional dalam rupiah bulat dan tercatat di audit', function () {
    $s = siapBiaya();

    catatBiaya($s)->assertSessionHasNoErrors()->assertRedirect('/expenses');

    $b = DB::table('expenses')->first();
    expect($b->amount)->toBe(1500000)->and($b->status)->toBe('recorded')->and($b->expense_date)->toStartWith('2025-06-10')->and($b->payee)->toBe('PLN')->and($b->created_by)->toBe($s['admin']->id)
        ->and(DB::table('audit_logs')->where('action', 'expense.recorded')->count())->toBe(1);
});

it('P2-08 masukan biaya yang salah ditolak', function () {
    $s = siapBiaya();

    catatBiaya($s, ['amount' => 0])->assertSessionHasErrors('amount');
    catatBiaya($s, ['amount' => '1.500.000'])->assertSessionHasErrors('amount');
    catatBiaya($s, ['amount' => '12,5'])->assertSessionHasErrors('amount');
    catatBiaya($s, ['description' => ''])->assertSessionHasErrors('description');
    catatBiaya($s, ['expense_date' => 'kemarin'])->assertSessionHasErrors('expense_date');
    catatBiaya($s, ['expense_date' => '2025-06-18'])->assertSessionHas('error_code', 'expense.date_future');
    catatBiaya($s, ['category_id' => strtoupper((string) Str::ulid())])->assertSessionHas('error_code', 'expense.category_invalid');
    DB::table('expense_categories')->update(['is_active' => false]);
    catatBiaya($s)->assertSessionHas('error_code', 'expense.category_invalid');
    expect(DB::table('expenses')->count())->toBe(0);
});

it('P2-08 biaya tidak dihapus: dibatalkan dengan alasan dan tidak masuk total', function () {
    $s = siapBiaya();
    catatBiaya($s);
    catatBiaya($s, ['amount' => 500000, 'description' => 'Salah ketik']);
    $salah = (string) DB::table('expenses')->where('description', 'Salah ketik')->value('id');

    $this->post("/expenses/{$salah}/void", [])->assertSessionHasErrors('reason');
    $this->post("/expenses/{$salah}/void", ['reason' => 'Dobel input'])->assertSessionHasNoErrors();
    $this->post("/expenses/{$salah}/void", ['reason' => 'Lagi'])->assertSessionHas('error_code', 'expense.already_void');

    expect(DB::table('expenses')->count())->toBe(2)->and(DB::table('expenses')->where('id', $salah)->value('status'))->toBe('void')
        ->and(DB::table('expenses')->where('id', $salah)->value('void_reason'))->toBe('Dobel input')
        ->and(DB::table('expenses')->where('id', $salah)->value('deleted_at'))->toBeNull()
        ->and(DB::table('audit_logs')->where('action', 'expense.voided')->count())->toBe(1);

    $this->get('/expenses?from=2025-06-01&to=2025-06-30')->assertInertia(fn ($p) => $p->component('Expenses/Index')->has('expenses', 2)->where('total_amount', 1500000)
        ->where('expenses', fn ($daftar) => collect($daftar)->pluck('voided')->sort()->values()->all() === [false, true]));
});

it('P2-08 daftar biaya memakai bulan berjalan bila rentang kosong dan dapat disaring per kategori', function () {
    $s = siapBiaya();
    catatBiaya($s, ['expense_date' => '2025-05-31']);
    catatBiaya($s, ['expense_date' => '2025-06-02']);
    catatBiaya($s, ['expense_date' => '2025-06-03', 'category_id' => $s['sewa']->id, 'amount' => 4000000, 'description' => 'Sewa Juni']);

    $this->get('/expenses')->assertInertia(fn ($p) => $p->where('filters.from', '2025-06-01')->where('filters.to', '2025-06-17')->has('expenses', 2)->where('total_amount', 5500000));
    $this->get('/expenses?category='.$s['sewa']->id)->assertInertia(fn ($p) => $p->has('expenses', 1)->where('expenses.0.description', 'Sewa Juni'));
});

it('P2-08 hak akses: Pemilik dan Admin melihat dan mencatat, Kasir dan Dokter ditolak', function () {
    $s = siapBiaya();
    catatBiaya($s);
    $biaya = (string) DB::table('expenses')->value('id');

    foreach (['pemilik', 'admin'] as $peran) {
        $this->actingAs($s[$peran])->get('/expenses')->assertOk()->assertInertia(fn ($p) => $p->where('can.manage', true));
    }
    $this->actingAs($s['kasir'])->get('/expenses')->assertForbidden();
    $this->get('/expenses/create')->assertForbidden();
    $this->post('/expenses', ['expense_date' => '2025-06-10', 'category_id' => $s['listrik']->id, 'amount' => 1, 'description' => 'x'])->assertForbidden();
    $this->post("/expenses/{$biaya}/void", ['reason' => 'x'])->assertForbidden();
    $this->actingAs($s['dokter'])->get('/expenses')->assertForbidden();
    expect(DB::table('expenses')->count())->toBe(1);
});

it('P2-08 kategori biaya dikelola sebagai master data oleh pemegang izin uang keluar', function () {
    $s = siapBiaya();

    $this->actingAs($s['admin'])->post('/settings/master/expense-categories', ['name' => 'Perlengkapan'])->assertSessionHasNoErrors();
    expect(DB::table('expense_categories')->where('name', 'Perlengkapan')->exists())->toBeTrue();
    $this->actingAs($s['kasir'])->post('/settings/master/expense-categories', ['name' => 'Y'])->assertForbidden();
});

it('P2-08 biaya hanya terlihat oleh klinik pemiliknya', function () {
    $s = siapBiaya();
    catatBiaya($s);
    $lain = siapBiaya();

    $this->actingAs($lain['admin'])->get('/expenses?from=2025-06-01&to=2025-06-30')->assertInertia(fn ($p) => $p->has('expenses', 0)->where('total_amount', 0));
    $biaya = (string) DB::table('expenses')->where('clinic_id', $s['k']->id)->value('id');
    $this->post("/expenses/{$biaya}/void", ['reason' => 'x'])->assertSessionHas('error_code', 'expense.not_found');
});

/**
 * Data laba rugi bulan berjalan: pendapatan Rp 1.000.000 (tagihan terbit 10/06), biaya lab Rp 300.000 (diterima 12/06), pembelian obat
 * Rp 200.000 (batch berharga beli 11/06), biaya operasional Rp 150.000 (sewa) + Rp 50.000 (listrik) serta satu biaya batal.
 * Bulan sebelumnya: hanya biaya operasional Rp 80.000.
 *
 * @return array<string, mixed>
 */
function siapLabaRugi(): array
{
    $s = siapBiaya();
    $k = $s['k'];
    $pasien = makePatient($k, 'Pasien Laba');
    $dokter = makeDoctor($k, 'drg. Sari');
    $kunjungan = strtoupper((string) Str::ulid());
    $now = now();
    DB::table('encounters')->insert(['id' => $kunjungan, 'clinic_id' => $k->id, 'patient_id' => $pasien, 'doctor_id' => $dokter->id, 'started_at' => '2025-06-10 03:00:00', 'status' => 'closed', 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('invoices')->insert([
        'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $k->id, 'number' => 'INV-2025-00001', 'patient_id' => $pasien, 'encounter_id' => $kunjungan, 'lifecycle' => 'issued', 'status' => 'paid',
        'subtotal_amount' => 1000000, 'discount_amount' => 0, 'total_amount' => 1000000, 'paid_amount' => 1000000, 'tax_amount' => 0, 'tax_basis_points' => 0,
        'issued_at' => '2025-06-10 03:30:00', 'created_at' => $now, 'updated_at' => $now,
    ]);

    $lab = new LaboratoryModel;
    $lab->forceFill(['name' => 'Lab Uji', 'is_active' => true])->save();
    DB::table('lab_orders')->insert([
        'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $k->id, 'order_no' => 'LAB-2025-00001', 'patient_id' => $pasien, 'doctor_id' => $dokter->id, 'laboratory_id' => $lab->id, 'work_type' => 'crown',
        'tooth_notes' => '', 'description' => 'Mahkota', 'status' => 'received', 'ordered_date' => '2025-06-01', 'received_date' => '2025-06-12', 'lab_cost_amount' => 300000, 'patient_charge_amount' => 900000,
        'version' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('lab_orders')->insert([
        'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $k->id, 'order_no' => 'LAB-2025-00002', 'patient_id' => $pasien, 'doctor_id' => $dokter->id, 'laboratory_id' => $lab->id, 'work_type' => 'crown',
        'tooth_notes' => '', 'description' => 'Belum diterima', 'status' => 'sent', 'ordered_date' => '2025-06-01', 'lab_cost_amount' => 999000, 'patient_charge_amount' => 0,
        'version' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $obat = makeMedicine($k);
    tenant()->set($k->id);
    DB::table('stock_batches')->insert([
        'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $k->id, 'medicine_id' => $obat->id, 'batch_no' => 'B1', 'received_quantity' => 100, 'remaining_quantity' => 100, 'unit_cost_amount' => 2000,
        'received_at' => '2025-06-11 03:00:00', 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('stock_batches')->insert([
        'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $k->id, 'medicine_id' => $obat->id, 'batch_no' => 'B2', 'received_quantity' => 50, 'remaining_quantity' => 50, 'unit_cost_amount' => null,
        'received_at' => '2025-06-11 04:00:00', 'created_at' => $now, 'updated_at' => $now,
    ]);

    catatBiaya($s, ['expense_date' => '2025-06-05', 'category_id' => $s['sewa']->id, 'amount' => 150000, 'description' => 'Sewa'])->assertSessionHasNoErrors();
    catatBiaya($s, ['expense_date' => '2025-06-06', 'amount' => 50000, 'description' => 'Listrik'])->assertSessionHasNoErrors();
    catatBiaya($s, ['expense_date' => '2025-06-07', 'amount' => 777000, 'description' => 'Salah'])->assertSessionHasNoErrors();
    $salah = (string) DB::table('expenses')->where('description', 'Salah')->value('id');
    test()->post("/expenses/{$salah}/void", ['reason' => 'Salah input'])->assertSessionHasNoErrors();
    catatBiaya($s, ['expense_date' => '2025-05-20', 'amount' => 80000, 'description' => 'Listrik Mei'])->assertSessionHasNoErrors();

    return $s;
}

/**
 * @param  array<string, mixed>  $s
 * @return array<string, mixed>
 */
function lihatLabaRugi(array $s, string $peran, string $query = 'from=2025-05-01&to=2025-06-30'): array
{
    tenant()->set($s['k']->id);
    $hasil = [];
    test()->actingAs($s[$peran])->get('/reports/profit-loss?'.$query)->assertOk()->assertInertia(function ($p) use (&$hasil) {
        $hasil = $p->toArray()['props'];

        return $p;
    });

    return $hasil['report'];
}

it('P2-08 laba rugi bulanan: pendapatan dikurangi biaya lab, pembelian obat, dan biaya operasional', function () {
    $s = siapLabaRugi();

    $r = lihatLabaRugi($s, 'pemilik');

    $metrik = array_column($r['metrics'], 'value', 'label');
    expect($metrik['Pendapatan'])->toBe(1000000)->and($metrik['Biaya lab'])->toBe(300000)->and($metrik['Pembelian obat dan bahan'])->toBe(200000)
        ->and($metrik['Biaya operasional'])->toBe(280000)->and($metrik['Laba (rugi) sederhana'])->toBe(220000)->and($metrik['Margin laba'])->toBe(2200);

    $bulan = collect($r['tables'][0]['rows'])->keyBy('period');
    expect($r['tables'][0]['rows'])->toHaveCount(2)
        ->and($bulan['2025-05'])->toMatchArray(['revenue' => 0, 'lab' => 0, 'purchases' => 0, 'operating' => 80000, 'profit' => -80000])
        ->and($bulan['2025-06'])->toMatchArray(['revenue' => 1000000, 'lab' => 300000, 'purchases' => 200000, 'operating' => 200000, 'profit' => 300000])
        ->and($r['tables'][0]['totals']['profit'])->toBe(220000);

    $kategori = collect($r['tables'][1]['rows'])->keyBy('category');
    expect($kategori['Sewa tempat']['total'])->toBe(150000)->and($kategori['Listrik dan air']['total'])->toBe(130000)->and($kategori['Listrik dan air']['count'])->toBe(2);
});

it('P2-08 laba rugi menghormati rentang: Juni saja tidak memuat Mei dan biaya batal tidak dihitung', function () {
    $s = siapLabaRugi();

    $r = lihatLabaRugi($s, 'admin', 'from=2025-06-01&to=2025-06-30');

    $metrik = array_column($r['metrics'], 'value', 'label');
    expect($metrik['Biaya operasional'])->toBe(200000)->and($metrik['Laba (rugi) sederhana'])->toBe(300000)->and($r['tables'][0]['rows'])->toHaveCount(1);
});

it('P2-08 laba rugi rugi bersih tampil negatif dan margin tidak negatif', function () {
    $s = siapLabaRugi();

    $r = lihatLabaRugi($s, 'pemilik', 'from=2025-05-01&to=2025-05-31');

    $metrik = array_column($r['metrics'], 'value', 'label');
    expect($metrik['Laba (rugi) sederhana'])->toBe(-80000)->and($metrik['Margin laba'])->toBe(0);
});

it('P2-08 laporan laba rugi hanya untuk pemegang laporan keuangan penuh', function () {
    $s = siapLabaRugi();

    $this->actingAs($s['kasir'])->get('/reports/profit-loss?from=2025-06-01&to=2025-06-30')->assertForbidden();
    $this->actingAs($s['dokter'])->get('/reports/profit-loss?from=2025-06-01&to=2025-06-30')->assertForbidden();
    $this->actingAs($s['pemilik'])->get('/reports')->assertOk()->assertSee('profit-loss');
});

it('P2-08 laba rugi dapat diekspor ke PDF dan Excel seperti laporan lain', function () {
    $s = siapLabaRugi();
    Storage::fake('private');

    foreach (['pdf', 'xlsx'] as $format) {
        $minta = $this->actingAs($s['pemilik'])->postJson('/internal-api/reports/profit-loss/export', ['format' => $format, 'from' => '2025-06-01', 'to' => '2025-06-30']);
        expect($minta->status())->toBeIn([200, 201, 202]);
    }
});
