<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Clinical\Domain\Events\ProcedureRetracted;
use Src\IdentityAccess\Domain\Role;
use Src\Pharmacy\Application\ProcedureMaterialService;
use Src\Pharmacy\Application\StockAlerts;
use Src\Pharmacy\Application\StockService;
use Src\Pharmacy\Infrastructure\Eloquent\SupplierModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 08:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Klinik dengan admin, kasir, pemasok, dan dua obat bersaldo nol.
 *
 * @return array<string, mixed>
 */
function siapStokLanjut(): array
{
    $k = makeClinic();
    $admin = makeUser(Role::Admin, $k);
    $kasir = makeUser(Role::Kasir, $k);
    $a = makeMedicine($k, ['name' => 'Anestesi Lidokain', 'unit' => 'ampul', 'min_stock' => 0]);
    $b = makeMedicine($k, ['name' => 'Kapas Gulung', 'unit' => 'bungkus', 'min_stock' => 0]);
    tenant()->set($k->id);
    $pemasok = new SupplierModel;
    $pemasok->forceFill(['name' => 'PT Dental Sejahtera', 'is_active' => true])->save();

    return compact('k', 'admin', 'kasir', 'a', 'b', 'pemasok');
}

function buatPesanan(array $s, array $o = []): string
{
    test()->actingAs($s['admin'])->post('/stock/purchase-orders', [
        'supplier_id' => $s['pemasok']->id,
        'expected_date' => '2025-06-20',
        'lines' => [
            ['medicine_id' => $s['a']->id, 'quantity' => 100, 'unit_cost_amount' => 12000],
            ['medicine_id' => $s['b']->id, 'quantity' => 20, 'unit_cost_amount' => 8500],
        ],
        ...$o,
    ])->assertSessionHasNoErrors();
    tenant()->set($s['k']->id);

    return (string) DB::table('purchase_orders')->orderByDesc('created_at')->orderByDesc('id')->value('id');
}

function itemPesanan(string $pesanan, string $obat): string
{
    return (string) DB::table('purchase_order_items')->where('purchase_order_id', $pesanan)->where('medicine_id', $obat)->value('id');
}

it('P2-05 pemasok dikelola sebagai master data dan hanya pemegang izin stok yang boleh mengubahnya', function () {
    $s = siapStokLanjut();

    $this->actingAs($s['admin'])->post('/settings/master/suppliers', ['name' => 'CV Medika Gigi', 'phone' => '021-555-0100', 'email' => 'order@medika.test'])->assertSessionHasNoErrors();
    expect(DB::table('suppliers')->where('name', 'CV Medika Gigi')->where('clinic_id', $s['k']->id)->exists())->toBeTrue();
    $this->post('/settings/master/suppliers', ['name' => 'X', 'email' => 'bukan-email'])->assertSessionHasErrors('email');

    $this->actingAs($s['kasir'])->post('/settings/master/suppliers', ['name' => 'Y'])->assertForbidden();
});

it('P2-05 pesanan pembelian: dibuat draft bernomor, dipesan, diterima sebagian lalu lengkap dengan batch, harga beli, dan pemasok', function () {
    $s = siapStokLanjut();
    $po = buatPesanan($s);

    expect(DB::table('purchase_orders')->where('id', $po)->value('po_no'))->toBe('PO-2025-00001')
        ->and(DB::table('purchase_orders')->where('id', $po)->value('status'))->toBe('draft');

    // Belum dipesan: tidak dapat menerima barang.
    $this->actingAs($s['admin'])->post("/stock/purchase-orders/{$po}/receive", ['lines' => [['item_id' => itemPesanan($po, $s['a']->id), 'quantity' => 10]]])->assertSessionHasErrors();

    $this->post("/stock/purchase-orders/{$po}/order")->assertSessionHasNoErrors();
    $this->post("/stock/purchase-orders/{$po}/receive", ['lines' => [
        ['item_id' => itemPesanan($po, $s['a']->id), 'quantity' => 60, 'batch_no' => 'LD-01', 'expiry_date' => '2026-03-31'],
        ['item_id' => itemPesanan($po, $s['b']->id), 'quantity' => 0],
    ]])->assertSessionHasNoErrors();

    tenant()->set($s['k']->id);
    expect(DB::table('purchase_orders')->where('id', $po)->value('status'))->toBe('partial')
        ->and((int) DB::table('stock_balances')->where('medicine_id', $s['a']->id)->value('quantity'))->toBe(60)
        ->and(DB::table('stock_balances')->where('medicine_id', $s['b']->id)->exists())->toBeFalse();
    $batch = DB::table('stock_batches')->where('medicine_id', $s['a']->id)->first();
    expect($batch->batch_no)->toBe('LD-01')->and((int) $batch->remaining_quantity)->toBe(60)->and((int) $batch->unit_cost_amount)->toBe(12000)
        ->and($batch->supplier_id)->toBe($s['pemasok']->id)->and($batch->purchase_order_id)->toBe($po);
    $mutasi = DB::table('stock_movements')->where('medicine_id', $s['a']->id)->first();
    expect($mutasi->reference_type)->toBe('purchase_order')->and($mutasi->reference_id)->toBe($po);

    // Melebihi sisa pesanan ditolak dan tidak mengubah stok.
    $this->post("/stock/purchase-orders/{$po}/receive", ['lines' => [['item_id' => itemPesanan($po, $s['a']->id), 'quantity' => 41]]])->assertSessionHasErrors();
    $this->post("/stock/purchase-orders/{$po}/cancel")->assertSessionHasErrors();

    $this->post("/stock/purchase-orders/{$po}/receive", ['lines' => [
        ['item_id' => itemPesanan($po, $s['a']->id), 'quantity' => 40, 'batch_no' => 'LD-02', 'expiry_date' => '2026-09-30'],
        ['item_id' => itemPesanan($po, $s['b']->id), 'quantity' => 20],
    ]])->assertSessionHasNoErrors();

    tenant()->set($s['k']->id);
    expect(DB::table('purchase_orders')->where('id', $po)->value('status'))->toBe('received')
        ->and((int) DB::table('stock_balances')->where('medicine_id', $s['a']->id)->value('quantity'))->toBe(100)
        ->and(DB::table('audit_logs')->where('action', 'purchase.received')->count())->toBe(2);
});

it('P2-05 tampilan pembelian memuat total dalam rupiah bulat dan pesanan dapat dibatalkan sebelum diterima', function () {
    $s = siapStokLanjut();
    $po = buatPesanan($s);

    $this->actingAs($s['admin'])->get("/stock/purchase-orders/{$po}")->assertInertia(fn ($p) => $p->component('Stock/Purchasing/Show')->where('order.total_amount', 1370000)->where('order.supplier_name', 'PT Dental Sejahtera')->has('order.items', 2));
    $this->get('/stock/purchase-orders')->assertInertia(fn ($p) => $p->component('Stock/Purchasing/Index')->has('orders', 1));

    $this->post("/stock/purchase-orders/{$po}/cancel")->assertSessionHasNoErrors();
    expect(DB::table('purchase_orders')->where('id', $po)->value('status'))->toBe('cancelled');
    $this->post("/stock/purchase-orders/{$po}/order")->assertSessionHasErrors();
});

it('P2-05 pesanan menolak pemasok nonaktif, obat ganda, dan akses tanpa izin', function () {
    $s = siapStokLanjut();

    $this->actingAs($s['admin'])->post('/stock/purchase-orders', ['supplier_id' => str_repeat('0', 26), 'lines' => [['medicine_id' => $s['a']->id, 'quantity' => 1, 'unit_cost_amount' => 100]]])->assertSessionHasErrors();
    $this->post('/stock/purchase-orders', ['supplier_id' => $s['pemasok']->id, 'lines' => [['medicine_id' => $s['a']->id, 'quantity' => 1, 'unit_cost_amount' => 100], ['medicine_id' => $s['a']->id, 'quantity' => 2, 'unit_cost_amount' => 100]]])->assertSessionHasErrors();
    $this->post('/stock/purchase-orders', ['supplier_id' => $s['pemasok']->id, 'lines' => []])->assertSessionHasErrors('lines');
    $this->post('/stock/purchase-orders', ['supplier_id' => $s['pemasok']->id, 'lines' => [['medicine_id' => $s['a']->id, 'quantity' => 1, 'unit_cost_amount' => '12,5']]])->assertSessionHasErrors('lines.0.unit_cost_amount');

    $this->actingAs($s['kasir'])->post('/stock/purchase-orders', ['supplier_id' => $s['pemasok']->id, 'lines' => [['medicine_id' => $s['a']->id, 'quantity' => 1, 'unit_cost_amount' => 100]]])->assertForbidden();
    $this->get('/stock/purchase-orders/create')->assertForbidden();
    expect(DB::table('purchase_orders')->count())->toBe(0);
});

it('P2-05 stok keluar memakai batch yang paling cepat kedaluwarsa lebih dulu dan batch tanpa tanggal terakhir', function () {
    $s = siapStokLanjut();
    $stok = app(StockService::class);
    $stok->receive($s['admin']->id, $s['a']->id, 10, 'TANPA', null, null);
    $stok->receive($s['admin']->id, $s['a']->id, 10, 'LAMBAT', '2026-12-31', null);
    $stok->receive($s['admin']->id, $s['a']->id, 10, 'CEPAT', '2025-07-01', null);

    $stok->dispose($s['admin']->id, $s['a']->id, 15, 'Pemakaian internal');

    $sisa = DB::table('stock_batches')->where('medicine_id', $s['a']->id)->pluck('remaining_quantity', 'batch_no')->map(fn ($v) => (int) $v)->all();
    expect($sisa)->toEqual(['TANPA' => 10, 'LAMBAT' => 5, 'CEPAT' => 0])
        ->and((int) DB::table('stock_balances')->where('medicine_id', $s['a']->id)->value('quantity'))->toBe(15);

    // Batch habis tidak muncul di daftar batch; urutan kedaluwarsa terdekat lebih dulu, tanpa tanggal terakhir.
    $this->actingAs($s['admin'])->get('/stock/batches')->assertInertia(fn ($p) => $p->component('Stock/Batches')->has('batches', 2)
        ->where('batches.0.batch_no', 'LAMBAT')->where('batches.0.remaining_quantity', 5)->where('batches.1.batch_no', 'TANPA'));
});

it('P2-05 peringatan kedaluwarsa memakai sisa per batch, bukan jumlah saat masuk', function () {
    $s = siapStokLanjut();
    $stok = app(StockService::class);
    $stok->receive($s['admin']->id, $s['a']->id, 10, 'CEPAT', '2025-06-30', null);
    $stok->receive($s['admin']->id, $s['a']->id, 10, 'LAMBAT', '2026-12-31', null);
    $stok->dispose($s['admin']->id, $s['a']->id, 4, 'Pemakaian');

    $kedaluwarsa = app(StockAlerts::class)->overview()['expiring'];
    expect($kedaluwarsa)->toHaveCount(1)->and($kedaluwarsa[0]['batch_no'])->toBe('CEPAT')->and($kedaluwarsa[0]['received'])->toBe(10)->and($kedaluwarsa[0]['quantity'])->toBe(6);

    $stok->dispose($s['admin']->id, $s['a']->id, 6, 'Pemakaian');
    expect(app(StockAlerts::class)->overview()['expiring'])->toBe([]);
});

it('P2-05 bahan habis pakai per tindakan: stok berkurang saat tindakan dicatat dan kembali bila dibatalkan', function () {
    $s = siapKunjungan();
    $admin = makeUser(Role::Admin, $s['klinik']);
    $obat = makeMedicine($s['klinik'], ['name' => 'Anestesi Lidokain', 'unit' => 'ampul', 'min_stock' => 0]);
    $tindakan = tindakan($s);
    app(StockService::class)->receive($admin->id, $obat->id, 10, 'LD-1', '2026-01-31', null);

    $this->actingAs($admin)->post('/settings/procedure-materials', ['procedure_id' => $tindakan->id, 'medicine_id' => $obat->id, 'quantity' => 2])->assertSessionHasNoErrors();
    $this->post('/settings/procedure-materials', ['procedure_id' => $tindakan->id, 'medicine_id' => $obat->id, 'quantity' => 0])->assertSessionHasErrors('quantity');

    $this->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $tindakan->id, 'tooth_fdi' => 46, 'quantity' => 3])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $entry = (string) DB::table('encounter_procedures')->value('id');

    expect((int) DB::table('stock_balances')->where('medicine_id', $obat->id)->value('quantity'))->toBe(4)
        ->and((int) DB::table('stock_batches')->where('medicine_id', $obat->id)->value('remaining_quantity'))->toBe(4);
    $mutasi = DB::table('stock_movements')->where('reference_type', 'encounter_procedure')->first();
    expect($mutasi->reference_id)->toBe($entry)->and((int) $mutasi->quantity)->toBe(-6)->and($mutasi->created_by)->toBe($s['dokter_user']->id);

    $this->delete("/encounters/{$s['kunjungan']}/procedures/{$entry}")->assertRedirect();
    tenant()->set($s['klinik']->id);
    expect((int) DB::table('stock_balances')->where('medicine_id', $obat->id)->value('quantity'))->toBe(10);

    // Mengulangi pembatalan tidak mengembalikan dua kali.
    app(ProcedureMaterialService::class)->procedureRetracted(new ProcedureRetracted($s['kunjungan'], $entry, new DateTimeImmutable, $s['dokter_user']->id));
    expect((int) DB::table('stock_balances')->where('medicine_id', $obat->id)->value('quantity'))->toBe(10);
});

it('P2-05 bahan habis pakai tidak menghentikan tindakan bila stok kurang; hanya yang tersedia diambil dan kekurangan tercatat', function () {
    $s = siapKunjungan();
    $admin = makeUser(Role::Admin, $s['klinik']);
    $obat = makeMedicine($s['klinik'], ['name' => 'Sarung Tangan', 'unit' => 'pasang', 'min_stock' => 0]);
    $tindakan = tindakan($s);
    app(StockService::class)->receive($admin->id, $obat->id, 3, null, null, null);
    $this->actingAs($admin)->post('/settings/procedure-materials', ['procedure_id' => $tindakan->id, 'medicine_id' => $obat->id, 'quantity' => 2])->assertSessionHasNoErrors();

    $this->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $tindakan->id, 'tooth_fdi' => 46, 'quantity' => 2])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);

    expect((int) DB::table('stock_balances')->where('medicine_id', $obat->id)->value('quantity'))->toBe(0)
        ->and(DB::table('encounter_procedures')->count())->toBe(1);
    $this->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $tindakan->id, 'tooth_fdi' => 47, 'quantity' => 1])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    expect(DB::table('audit_logs')->where('action', 'stock.material_short')->count())->toBe(1)
        ->and(DB::table('encounter_procedures')->count())->toBe(2);
});

it('P2-05 bahan tindakan dapat dilepas dan dikelola hanya oleh pemegang stock.manage', function () {
    $s = siapKunjungan();
    $admin = makeUser(Role::Admin, $s['klinik']);
    $kasir = makeUser(Role::Kasir, $s['klinik']);
    $obat = makeMedicine($s['klinik']);
    $tindakan = tindakan($s);

    $this->actingAs($admin)->post('/settings/procedure-materials', ['procedure_id' => $tindakan->id, 'medicine_id' => $obat->id, 'quantity' => 1])->assertSessionHasNoErrors();
    $this->get('/settings/procedure-materials')->assertInertia(fn ($p) => $p->component('Settings/ProcedureMaterials')->has('materials', 1)->where('materials.0.quantity', 1));

    $bahan = (string) DB::table('procedure_materials')->value('id');
    $this->actingAs($kasir)->post("/settings/procedure-materials/{$bahan}/remove")->assertForbidden();
    $this->actingAs($admin)->post("/settings/procedure-materials/{$bahan}/remove")->assertSessionHasNoErrors();
    $this->get('/settings/procedure-materials')->assertInertia(fn ($p) => $p->has('materials', 0));
    expect(DB::table('procedure_materials')->where('id', $bahan)->value('is_active'))->toBeFalsy();

    // Mengaktifkan ulang pasangan yang sama memakai baris yang ada.
    $this->post('/settings/procedure-materials', ['procedure_id' => $tindakan->id, 'medicine_id' => $obat->id, 'quantity' => 4])->assertSessionHasNoErrors();
    expect(DB::table('procedure_materials')->count())->toBe(1)->and((int) DB::table('procedure_materials')->value('quantity'))->toBe(4);
});

it('P2-05 opname terjadwal: jadwal membuka satu sesi per jatuh tempo, memberi tahu staf, dan hasil hitung menyesuaikan stok', function () {
    $s = siapStokLanjut();
    $stok = app(StockService::class);
    $stok->receive($s['admin']->id, $s['a']->id, 50, 'B1', null, null);
    $stok->receive($s['admin']->id, $s['b']->id, 10, null, null, null);

    $this->actingAs($s['admin'])->post('/stock/counts/schedule', ['enabled' => true, 'interval_days' => 3])->assertSessionHasErrors('interval_days');
    $this->post('/stock/counts/schedule', ['enabled' => true, 'interval_days' => 30])->assertSessionHasNoErrors();

    $this->artisan('sikg:stock:count')->assertSuccessful();
    $this->artisan('sikg:stock:count')->assertSuccessful();
    tenant()->set($s['k']->id);

    expect(DB::table('stock_count_sessions')->count())->toBe(1)
        ->and(DB::table('stock_count_sessions')->value('source'))->toBe('schedule')
        ->and(DB::table('stock_count_schedules')->value('next_due_date'))->toStartWith('2025-07-16')
        ->and(DB::table('stock_count_lines')->count())->toBe(2)
        ->and(DB::table('notifications')->where('kind', 'stock.count_due')->where('permission', 'stock.manage')->count())->toBe(1);

    $sesi = (string) DB::table('stock_count_sessions')->value('id');
    $baris = DB::table('stock_count_lines')->pluck('id', 'medicine_id')->all();
    $this->post("/stock/counts/{$sesi}/save", ['counts' => [$baris[$s['a']->id] => 47, $baris[$s['b']->id] => null]])->assertSessionHasNoErrors();
    $this->get("/stock/counts/{$sesi}")->assertInertia(fn ($p) => $p->component('Stock/Counts/Show')->has('lines', 2)
        ->where('lines.0.name', 'Anestesi Lidokain')->where('lines.0.system_quantity', 50)->where('lines.0.counted_quantity', 47)->where('lines.0.difference', -3));

    $this->post("/stock/counts/{$sesi}/complete")->assertSessionHasNoErrors();
    tenant()->set($s['k']->id);
    expect((int) DB::table('stock_balances')->where('medicine_id', $s['a']->id)->value('quantity'))->toBe(47)
        ->and((int) DB::table('stock_balances')->where('medicine_id', $s['b']->id)->value('quantity'))->toBe(10)
        ->and(DB::table('stock_count_sessions')->value('status'))->toBe('completed')
        ->and(DB::table('stock_movements')->where('medicine_id', $s['a']->id)->where('type', 'adjustment')->value('reason'))->toBe('Opname 2025-06-16')
        ->and((int) DB::table('stock_batches')->where('medicine_id', $s['a']->id)->sum('remaining_quantity'))->toBe(47);

    // Sesi yang sudah ditutup tidak dapat diubah lagi.
    $this->post("/stock/counts/{$sesi}/save", ['counts' => [$baris[$s['a']->id] => 1]])->assertSessionHasErrors();
    $this->post("/stock/counts/{$sesi}/complete")->assertSessionHasErrors();
});

it('P2-05 sesi opname terbuka mencegah sesi ganda; manual dapat dimulai dan dibatalkan; jadwal mati tidak membuka sesi', function () {
    $s = siapStokLanjut();
    $this->artisan('sikg:stock:count')->assertSuccessful();
    tenant()->set($s['k']->id);
    expect(DB::table('stock_count_sessions')->count())->toBe(0);

    $this->actingAs($s['admin'])->post('/stock/counts', ['note' => 'Opname akhir bulan'])->assertSessionHasNoErrors();
    $this->post('/stock/counts')->assertSessionHasErrors();
    $sesi = (string) DB::table('stock_count_sessions')->value('id');
    expect(DB::table('stock_count_sessions')->value('source'))->toBe('manual');

    $this->post("/stock/counts/{$sesi}/complete")->assertSessionHasErrors();
    $this->post("/stock/counts/{$sesi}/cancel")->assertSessionHasNoErrors();
    expect(DB::table('stock_count_sessions')->value('status'))->toBe('cancelled');

    $this->actingAs($s['kasir'])->post('/stock/counts')->assertForbidden();
});

it('P2-05 jadwal berjalan saat jatuh tempo berikutnya tiba dan tidak menumpuk sesi bila sesi lama masih terbuka', function () {
    $s = siapStokLanjut();
    $this->actingAs($s['admin'])->post('/stock/counts/schedule', ['enabled' => true, 'interval_days' => 7])->assertSessionHasNoErrors();
    $this->artisan('sikg:stock:count')->assertSuccessful();

    Carbon::setTestNow(Carbon::parse('2025-06-23 08:00:00', 'Asia/Jakarta'));
    $this->artisan('sikg:stock:count')->assertSuccessful();
    tenant()->set($s['k']->id);
    expect(DB::table('stock_count_sessions')->count())->toBe(1)
        ->and(DB::table('stock_count_schedules')->value('next_due_date'))->toStartWith('2025-06-30');

    $sesi = (string) DB::table('stock_count_sessions')->value('id');
    $this->flushSession();
    $this->actingAs($s['admin'])->post("/stock/counts/{$sesi}/cancel")->assertSessionHasNoErrors();
    expect(DB::table('stock_count_sessions')->value('status'))->toBe('cancelled');
    Carbon::setTestNow(Carbon::parse('2025-06-30 08:00:00', 'Asia/Jakarta'));
    $this->artisan('sikg:stock:count')->assertSuccessful();
    tenant()->set($s['k']->id);
    expect(DB::table('stock_count_sessions')->count())->toBe(2);
});
