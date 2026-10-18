<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Clinic\Infrastructure\Eloquent\BranchModel;
use Src\IdentityAccess\Domain\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-17 08:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Klinik dengan dua cabang (Utara, Selatan). Pemilik dan Admin pusat tidak terikat cabang; Admin Utara dan Admin Selatan terikat.
 *
 * @return array<string, mixed>
 */
function siapCabang(): array
{
    $k = makeClinic();
    tenant()->set($k->id);
    $utara = new BranchModel;
    $utara->forceFill(['name' => 'Cabang Utara', 'is_active' => true])->save();
    $selatan = new BranchModel;
    $selatan->forceFill(['name' => 'Cabang Selatan', 'is_active' => true])->save();

    return [
        'k' => $k, 'utara' => $utara, 'selatan' => $selatan,
        'pemilik' => makeUser(Role::Pemilik, $k), 'pusat' => makeUser(Role::Admin, $k),
        'admin_utara' => makeUser(Role::Admin, $k, $utara), 'admin_selatan' => makeUser(Role::Admin, $k, $selatan),
        'obat' => makeMedicine($k),
    ];
}

/** @return array<string, mixed> */
function bagikanProps(string $url = '/dashboard'): array
{
    $hasil = [];
    test()->get($url)->assertOk()->assertInertia(function ($p) use (&$hasil) {
        $hasil = $p->toArray()['props'];

        return $p;
    });

    return $hasil;
}

function saldoCabang(string $obatId, ?string $cabangId): int
{
    $q = DB::table('stock_balances')->where('medicine_id', $obatId);
    $cabangId === null ? $q->whereNull('branch_id') : $q->where('branch_id', $cabangId);

    return (int) $q->value('quantity');
}

it('P3-04 Admin mengelola cabang sebagai master data; nama unik dan dapat dinonaktifkan', function () {
    $k = makeClinic();
    $admin = makeUser(Role::Admin, $k);

    $this->actingAs($admin)->post('/settings/master/branches', ['name' => 'Cabang Barat', 'address' => 'Jl. Contoh 1'])->assertSessionHasNoErrors();
    $this->post('/settings/master/branches', ['name' => 'Cabang Barat'])->assertSessionHasErrors('name');
    $this->post('/settings/master/branches', ['name' => ''])->assertSessionHasErrors('name');
    tenant()->set($k->id);
    $cabang = BranchModel::query()->where('name', 'Cabang Barat')->firstOrFail();
    expect($cabang->address)->toBe('Jl. Contoh 1')->and($cabang->is_active)->toBeTrue();

    $this->put("/settings/master/branches/{$cabang->id}", ['name' => 'Cabang Barat Raya', 'address' => ''])->assertSessionHasNoErrors();
    $this->post("/settings/master/branches/{$cabang->id}/deactivate")->assertSessionHasNoErrors();
    tenant()->set($k->id);
    expect(BranchModel::query()->findOrFail($cabang->id)->is_active)->toBeFalse()->and(DB::table('audit_logs')->where('action', 'like', 'branch.%')->count())->toBeGreaterThanOrEqual(3);

    $this->actingAs(makeUser(Role::Kasir, $k))->get('/settings/master/branches')->assertForbidden();
});

it('P3-04 pengguna tanpa ikatan cabang memilih cabang aktif; pilihan hidup di sesi dan mengubah konteks', function () {
    $s = siapCabang();
    $this->actingAs($s['pemilik']);

    $awal = bagikanProps()['branch'];
    expect($awal['active_id'])->toBeNull()->and($awal['locked'])->toBeFalse()->and($awal['options'])->toHaveCount(2);

    $this->post('/branch/switch', ['branch_id' => $s['utara']->id])->assertSessionHasNoErrors();
    $props = bagikanProps()['branch'];
    expect($props['active_id'])->toBe($s['utara']->id)->and($props['active_name'])->toBe('Cabang Utara');
    expect(DB::table('audit_logs')->where('action', 'branch.switched')->count())->toBe(1);

    $this->post('/branch/switch', ['branch_id' => null])->assertSessionHasNoErrors();
    expect(bagikanProps()['branch']['active_id'])->toBeNull();
});

it('P3-04 pilihan cabang tidak sah ditolak, pengguna terikat cabang tidak dapat berpindah, dan cabang nonaktif dilepas', function () {
    $s = siapCabang();

    $this->actingAs($s['admin_utara'])->post('/branch/switch', ['branch_id' => $s['selatan']->id])->assertSessionHas('error_code', 'branch.fixed');
    $terikat = bagikanProps()['branch'];
    expect($terikat['locked'])->toBeTrue()->and($terikat['active_id'])->toBe($s['utara']->id)->and($terikat['options'])->toHaveCount(2);

    $this->actingAs($s['pemilik'])->post('/branch/switch', ['branch_id' => str_repeat('0', 26)])->assertSessionHas('error_code', 'branch.not_found');
    $this->post('/branch/switch', ['branch_id' => 'pendek'])->assertSessionHasErrors('branch_id');

    $this->post('/branch/switch', ['branch_id' => $s['selatan']->id])->assertSessionHasNoErrors();
    tenant()->set($s['k']->id);
    BranchModel::query()->whereKey($s['selatan']->id)->update(['is_active' => false]);
    expect(bagikanProps()['branch']['active_id'])->toBeNull();
    $this->post('/branch/switch', ['branch_id' => $s['selatan']->id])->assertSessionHas('error_code', 'branch.not_found');

    auth()->logout();
    $this->post('/branch/switch', ['branch_id' => $s['utara']->id])->assertRedirect('/login');
});

it('P3-04 klinik satu cabang tidak menampilkan pemilih cabang', function () {
    $k = makeClinic();
    tenant()->set($k->id);
    (new BranchModel)->forceFill(['name' => 'Satu-satunya', 'is_active' => true])->save();

    $this->actingAs(makeUser(Role::Pemilik, $k));

    expect(bagikanProps()['branch']['options'])->toBe([]);
});

it('P3-04 stok milik cabang: penerimaan cabang tidak menyentuh cabang lain, dan saldo digabung untuk pengguna tanpa cabang', function () {
    $s = siapCabang();
    $obat = $s['obat']->id;

    $this->actingAs($s['admin_utara'])->post('/stock/movements', ['medicine_id' => $obat, 'type' => 'in', 'quantity' => 40, 'reason' => 'Uji'])->assertSessionHasNoErrors();
    $this->actingAs($s['admin_selatan'])->post('/stock/movements', ['medicine_id' => $obat, 'type' => 'in', 'quantity' => 25, 'reason' => 'Uji'])->assertSessionHasNoErrors();
    $this->actingAs($s['pusat'])->post('/stock/movements', ['medicine_id' => $obat, 'type' => 'in', 'quantity' => 100, 'reason' => 'Uji'])->assertSessionHasNoErrors();

    expect(saldoCabang($obat, $s['utara']->id))->toBe(40)->and(saldoCabang($obat, $s['selatan']->id))->toBe(25)->and(saldoCabang($obat, null))->toBe(100)
        ->and(DB::table('stock_balances')->where('medicine_id', $obat)->count())->toBe(3);

    $this->actingAs($s['admin_utara'])->get('/stock')->assertInertia(fn ($p) => $p->where('medicines.0.quantity', 40));
    $this->actingAs($s['admin_selatan'])->get('/stock')->assertInertia(fn ($p) => $p->where('medicines.0.quantity', 25));
    $this->actingAs($s['pusat'])->get('/stock')->assertInertia(fn ($p) => $p->where('medicines.0.quantity', 165));

    // Stok keluar di Utara memakai batch Utara saja.
    $this->actingAs($s['admin_utara'])->post('/stock/movements', ['medicine_id' => $obat, 'type' => 'out', 'quantity' => 15, 'reason' => 'Dipakai'])->assertSessionHasNoErrors();
    expect(saldoCabang($obat, $s['utara']->id))->toBe(25)->and(saldoCabang($obat, null))->toBe(100)->and(saldoCabang($obat, $s['selatan']->id))->toBe(25);
    $this->post('/stock/movements', ['medicine_id' => $obat, 'type' => 'out', 'quantity' => 30, 'reason' => 'Terlalu banyak'])->assertSessionHas('error_code', 'stock.insufficient');
});

it('P3-04 transfer memindahkan jumlah dan batch menurut FEFO, tercatat dua mutasi, dan batch tujuan tidak dihitung pembelian', function () {
    $s = siapCabang();
    $obat = $s['obat']->id;
    $this->actingAs($s['pusat']);
    $this->post('/stock/movements', ['medicine_id' => $obat, 'type' => 'in', 'quantity' => 20, 'batch_no' => 'B-DEKAT', 'expiry_date' => '2025-12-01'])->assertSessionHasNoErrors();
    $this->post('/stock/movements', ['medicine_id' => $obat, 'type' => 'in', 'quantity' => 80, 'batch_no' => 'B-JAUH', 'expiry_date' => '2026-12-01'])->assertSessionHasNoErrors();
    DB::table('stock_batches')->where('batch_no', 'B-JAUH')->update(['unit_cost_amount' => 2500]);

    $this->post('/stock/transfers', ['medicine_id' => $obat, 'quantity' => 30, 'from_branch_id' => '', 'to_branch_id' => $s['utara']->id, 'reason' => 'Isi awal'])->assertSessionHasNoErrors();

    expect(saldoCabang($obat, null))->toBe(70)->and(saldoCabang($obat, $s['utara']->id))->toBe(30);
    // FEFO di asal: B-DEKAT habis (20), B-JAUH berkurang 10.
    expect((int) DB::table('stock_batches')->whereNull('branch_id')->where('batch_no', 'B-DEKAT')->value('remaining_quantity'))->toBe(0)
        ->and((int) DB::table('stock_batches')->whereNull('branch_id')->where('batch_no', 'B-JAUH')->value('remaining_quantity'))->toBe(70);
    $tujuan = DB::table('stock_batches')->where('branch_id', $s['utara']->id)->orderBy('expiry_date')->get();
    expect($tujuan)->toHaveCount(2)->and($tujuan[0]->batch_no)->toBe('B-DEKAT')->and((int) $tujuan[0]->remaining_quantity)->toBe(20)->and($tujuan[0]->expiry_date)->toStartWith('2025-12-01')
        ->and($tujuan[1]->batch_no)->toBe('B-JAUH')->and((int) $tujuan[1]->remaining_quantity)->toBe(10)->and((int) $tujuan[1]->unit_cost_amount)->toBe(2500)->and($tujuan[1]->transfer_id)->not->toBeNull();

    $gerak = DB::table('stock_movements')->where('reference_type', 'stock_transfer')->orderBy('quantity')->get();
    expect($gerak)->toHaveCount(2)->and($gerak[0]->type)->toBe('out')->and((int) $gerak[0]->quantity)->toBe(-30)->and($gerak[0]->branch_id)->toBeNull()->and((int) $gerak[0]->balance_after)->toBe(70)
        ->and($gerak[1]->type)->toBe('in')->and((int) $gerak[1]->quantity)->toBe(30)->and($gerak[1]->branch_id)->toBe($s['utara']->id)->and((int) $gerak[1]->balance_after)->toBe(30);
    $transfer = DB::table('stock_transfers')->first();
    expect($transfer->from_branch_id)->toBeNull()->and($transfer->to_branch_id)->toBe($s['utara']->id)->and((int) $transfer->quantity)->toBe(30)->and($transfer->reason)->toBe('Isi awal')
        ->and(DB::table('audit_logs')->where('action', 'stock.transferred')->count())->toBe(1);

    $this->get('/stock/transfers')->assertOk()->assertInertia(fn ($p) => $p->component('Stock/Transfers')->where('transfers.0.quantity', 30)->where('transfers.0.from', 'Gudang pusat')->where('transfers.0.to', 'Cabang Utara')
        ->where('can.manage', true)->has('balances', 2));
});

it('P3-04 pengguna terikat cabang hanya mengirim dari cabangnya; saldo kurang, cabang sama, dan cabang tak dikenal ditolak', function () {
    $s = siapCabang();
    $obat = $s['obat']->id;
    $this->actingAs($s['admin_utara'])->post('/stock/movements', ['medicine_id' => $obat, 'type' => 'in', 'quantity' => 10])->assertSessionHasNoErrors();

    $kirim = fn (array $o) => $this->post('/stock/transfers', ['medicine_id' => $obat, 'quantity' => 4, 'from_branch_id' => $s['utara']->id, 'to_branch_id' => $s['selatan']->id, ...$o]);

    $kirim(['from_branch_id' => ''])->assertSessionHas('error_code', 'stock.transfer_source');
    $kirim(['from_branch_id' => $s['selatan']->id, 'to_branch_id' => $s['utara']->id])->assertSessionHas('error_code', 'stock.transfer_source');
    $kirim(['to_branch_id' => $s['utara']->id])->assertSessionHas('error_code', 'stock.transfer_same_branch');
    $kirim(['to_branch_id' => str_repeat('0', 26)])->assertSessionHas('error_code', 'stock.branch_not_found');
    $kirim(['quantity' => 11])->assertSessionHas('error_code', 'stock.insufficient');
    $kirim(['quantity' => 0])->assertSessionHasErrors('quantity');
    $kirim(['medicine_id' => str_repeat('0', 26)])->assertSessionHas('error_code', 'stock.medicine_not_found');
    expect(DB::table('stock_transfers')->count())->toBe(0);

    $kirim([])->assertSessionHasNoErrors();
    expect(saldoCabang($obat, $s['utara']->id))->toBe(6)->and(saldoCabang($obat, $s['selatan']->id))->toBe(4);

    // Selatan melihat transfer yang melibatkannya, Pusat melihat semuanya, dan Selatan tidak melihat saldo Utara.
    $this->actingAs($s['admin_selatan'])->get('/stock/transfers')->assertInertia(fn ($p) => $p->has('transfers', 1)->has('balances', 1)->where('bound_branch_id', $s['selatan']->id));
});

it('P3-04 transfer dua arah tidak menggandakan saldo dan kunci unik per cabang dijaga', function () {
    $s = siapCabang();
    $obat = $s['obat']->id;
    $this->actingAs($s['pusat'])->post('/stock/movements', ['medicine_id' => $obat, 'type' => 'in', 'quantity' => 50])->assertSessionHasNoErrors();
    $this->post('/stock/transfers', ['medicine_id' => $obat, 'quantity' => 20, 'from_branch_id' => '', 'to_branch_id' => $s['utara']->id])->assertSessionHasNoErrors();
    $this->post('/stock/transfers', ['medicine_id' => $obat, 'quantity' => 5, 'from_branch_id' => $s['utara']->id, 'to_branch_id' => $s['selatan']->id])->assertSessionHasNoErrors();
    $this->post('/stock/transfers', ['medicine_id' => $obat, 'quantity' => 5, 'from_branch_id' => $s['selatan']->id, 'to_branch_id' => ''])->assertSessionHasNoErrors();

    expect(saldoCabang($obat, null))->toBe(35)->and(saldoCabang($obat, $s['utara']->id))->toBe(15)->and(saldoCabang($obat, $s['selatan']->id))->toBe(0)
        ->and((int) DB::table('stock_balances')->where('medicine_id', $obat)->sum('quantity'))->toBe(50);

    // Dua saldo untuk obat dan cabang yang sama (termasuk tanpa cabang) ditolak basis data.
    $baris = fn (?string $cabang) => [
        'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $s['k']->id, 'branch_id' => $cabang, 'medicine_id' => $obat, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
    ];
    expect(fn () => DB::table('stock_balances')->insert($baris(null)))->toThrow(QueryException::class)
        ->and(fn () => DB::table('stock_balances')->insert($baris($s['utara']->id)))->toThrow(QueryException::class);
});

it('P3-04 laporan stok dan peringatan mengikuti cabang aktif tanpa menggandakan baris', function () {
    $s = siapCabang();
    $obat = $s['obat'];
    DB::table('medicines')->where('id', $obat->id)->update(['min_stock' => 30]);
    $this->actingAs($s['admin_utara'])->post('/stock/movements', ['medicine_id' => $obat->id, 'type' => 'in', 'quantity' => 40])->assertSessionHasNoErrors();
    $this->actingAs($s['admin_selatan'])->post('/stock/movements', ['medicine_id' => $obat->id, 'type' => 'in', 'quantity' => 25])->assertSessionHasNoErrors();

    $metrik = function ($pengguna) {
        $hasil = [];
        test()->actingAs($pengguna)->get('/reports/stock?from=2025-06-01&to=2025-06-30')->assertOk()->assertInertia(function ($p) use (&$hasil) {
            $hasil = $p->toArray()['props'];

            return $p;
        });

        return $hasil['report'];
    };

    // Pemilik melihat satu baris obat dengan jumlah gabungan, bukan satu baris per cabang.
    $semua = $metrik($s['pemilik']);
    $barisObat = array_values(array_filter($semua['tables'][0]['rows'], fn ($r) => str_starts_with((string) $r['name'], $obat->name)));
    expect($barisObat)->toHaveCount(1)->and($barisObat[0]['quantity'])->toBe(65);

    $selatan = $metrik($s['admin_selatan']);
    $barisSelatan = array_values(array_filter($selatan['tables'][0]['rows'], fn ($r) => str_starts_with((string) $r['name'], $obat->name)));
    expect($barisSelatan[0]['quantity'])->toBe(25);
});

it('P3-04 konsolidasi cabang (LAP-12): satu baris per cabang, total, dan dibatasi cabang aktif', function () {
    $s = siapCabang();
    $k = $s['k'];
    tenant()->set($k->id);
    $dokter = makeDoctor($k, 'drg. Konsolidasi');
    $now = now();
    $utc = fn (string $lokal) => Carbon::parse($lokal, 'Asia/Jakarta')->utc()->format('Y-m-d H:i:s');
    $metodeId = strtoupper((string) Str::ulid());
    DB::table('payment_methods')->insert(['id' => $metodeId, 'clinic_id' => $k->id, 'name' => 'Tunai', 'type' => 'cash', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

    $tambah = function (?string $cabang, int $total, int $biaya) use ($k, $dokter, $metodeId, $now, $utc): void {
        $pasien = makePatient($k, 'Pasien '.uniqid());
        tenant()->set($k->id);
        $kunjungan = strtoupper((string) Str::ulid());
        DB::table('encounters')->insert(['id' => $kunjungan, 'clinic_id' => $k->id, 'branch_id' => $cabang, 'patient_id' => $pasien, 'doctor_id' => $dokter->id, 'started_at' => $utc('2025-06-16 09:00'), 'status' => 'closed', 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $faktur = strtoupper((string) Str::ulid());
        DB::table('invoices')->insert([
            'id' => $faktur, 'clinic_id' => $k->id, 'branch_id' => $cabang, 'number' => 'INV-'.substr($faktur, -6), 'patient_id' => $pasien, 'encounter_id' => $kunjungan, 'lifecycle' => 'issued', 'status' => 'paid',
            'subtotal_amount' => $total, 'discount_amount' => 0, 'total_amount' => $total, 'paid_amount' => $total, 'tax_amount' => 0, 'tax_basis_points' => 0, 'issued_at' => $utc('2025-06-16 10:00'), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $kasir = makeUser(Role::Kasir, $k);
        DB::table('payments')->insert(['id' => strtoupper((string) Str::ulid()), 'clinic_id' => $k->id, 'branch_id' => $cabang, 'invoice_id' => $faktur, 'payment_method_id' => $metodeId, 'type' => 'pay', 'amount' => $total, 'paid_at' => $utc('2025-06-16 10:05'), 'received_by' => $kasir->id, 'idempotency_key' => 'uji-'.$faktur, 'created_at' => $now]);
        if ($biaya > 0) {
            DB::table('expense_categories')->insertOrIgnore(['id' => '0000000000000000000000EXPC', 'clinic_id' => $k->id, 'name' => 'Umum', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('expenses')->insert(['id' => strtoupper((string) Str::ulid()), 'clinic_id' => $k->id, 'branch_id' => $cabang, 'expense_date' => '2025-06-16', 'category_id' => '0000000000000000000000EXPC', 'amount' => $biaya, 'description' => 'Uji', 'status' => 'recorded', 'created_at' => $now, 'updated_at' => $now]);
        }
    };
    $tambah($s['utara']->id, 600000, 100000);
    $tambah($s['utara']->id, 400000, 0);
    $tambah($s['selatan']->id, 250000, 50000);

    $laporan = function ($pengguna, ?string $cabang = null) {
        $hasil = [];
        test()->actingAs($pengguna);
        if ($cabang !== null) {
            test()->post('/branch/switch', ['branch_id' => $cabang]);
        }
        test()->get('/reports/branch-summary?from=2025-06-01&to=2025-06-30')->assertOk()->assertInertia(function ($p) use (&$hasil) {
            $hasil = $p->toArray()['props'];

            return $p;
        });

        return $hasil['report'];
    };

    $semua = $laporan($s['pemilik']);
    $baris = array_column($semua['tables'][0]['rows'], null, 'branch');
    expect($baris)->toHaveKeys(['Cabang Utara', 'Cabang Selatan'])
        ->and($baris['Cabang Utara']['revenue'])->toBe(1000000)->and($baris['Cabang Utara']['invoices'])->toBe(2)->and($baris['Cabang Utara']['receipts'])->toBe(1000000)
        ->and($baris['Cabang Utara']['visits'])->toBe(2)->and($baris['Cabang Utara']['expenses'])->toBe(100000)->and($baris['Cabang Utara']['balance'])->toBe(900000)
        ->and($baris['Cabang Selatan']['revenue'])->toBe(250000)->and($baris['Cabang Selatan']['expenses'])->toBe(50000)
        ->and($semua['tables'][0]['totals']['revenue'])->toBe(1250000)->and($semua['tables'][0]['totals']['expenses'])->toBe(150000);

    // Admin Selatan (terikat) hanya melihat cabangnya; Pemilik yang memilih Utara hanya melihat Utara.
    $selatan = $laporan($s['admin_selatan']);
    expect(array_column($selatan['tables'][0]['rows'], 'branch'))->toBe(['Cabang Selatan']);
    $utara = $laporan($s['pemilik'], $s['utara']->id);
    expect(array_column($utara['tables'][0]['rows'], 'branch'))->toBe(['Cabang Utara'])->and($utara['tables'][0]['totals']['revenue'])->toBe(1000000);
});
