<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Src\IdentityAccess\Domain\Role;
use Src\Patient\Infrastructure\Eloquent\PatientMedicalHistoryModel;
use Src\Pharmacy\Domain\Events\MedicineDispensed;
use Src\Pharmacy\Domain\Events\MedicineReturned;
use Src\Pharmacy\Infrastructure\Eloquent\MedicineModel;
use Src\Pharmacy\Infrastructure\Eloquent\PrescriptionItemModel;
use Src\Pharmacy\Infrastructure\Eloquent\PrescriptionModel;
use Src\Pharmacy\Infrastructure\Eloquent\StockBalanceModel;
use Src\Pharmacy\Infrastructure\Eloquent\StockMovementModel;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function saldo(MedicineModel $m): int
{
    return (int) StockBalanceModel::query()->where('medicine_id', $m->id)->value('quantity');
}

function barisResep(MedicineModel $m, array $o = []): array
{
    return ['medicine_id' => $m->id, 'dose' => '500 mg', 'frequency' => '3 x sehari', 'duration' => '5 hari', 'instructions' => 'Sesudah makan', 'quantity' => 15, 'dispense_in_clinic' => false, ...$o];
}

// --- OBT-05: stok ---

it('OBT-05 stok masuk menambah saldo dan tercatat sebagai mutasi bertanda dengan batch dan kedaluwarsa', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $m = makeMedicine($klinik);

    test()->actingAs($admin)->post('/stock/movements', ['medicine_id' => $m->id, 'type' => 'in', 'quantity' => 100, 'batch_no' => 'B-01', 'expiry_date' => '2026-12-31', 'reason' => 'Pembelian'])->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    $mut = StockMovementModel::query()->firstOrFail();
    expect(saldo($m))->toBe(100)->and($mut->type)->toBe('in')->and($mut->quantity)->toBe(100)->and($mut->balance_after)->toBe(100)
        ->and($mut->batch_no)->toBe('B-01')->and($mut->expiry_date?->toDateString())->toBe('2026-12-31')->and($mut->created_by)->toBe($admin->id)
        ->and(AuditLogModel::query()->where('action', 'stock.received')->exists())->toBeTrue();
});

it('OBT-05 stok keluar mengurangi saldo dan alasan wajib', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $m = makeMedicine($klinik);
    isiStok($klinik, $m, 50);

    test()->actingAs($admin)->post('/stock/movements', ['medicine_id' => $m->id, 'type' => 'out', 'quantity' => 10])->assertSessionHasErrors('domain');
    test()->actingAs($admin)->post('/stock/movements', ['medicine_id' => $m->id, 'type' => 'out', 'quantity' => 10, 'reason' => 'Pemakaian tindakan'])->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    expect(saldo($m))->toBe(40);
    $keluar = StockMovementModel::query()->where('type', 'out')->firstOrFail();
    expect($keluar->quantity)->toBe(-10)->and($keluar->balance_after)->toBe(40)->and($keluar->reason)->toBe('Pemakaian tindakan');
});

it('OBT-05 stok tidak boleh negatif: kekurangan ditolak dengan kode stock.insufficient', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $m = makeMedicine($klinik);
    isiStok($klinik, $m, 5);

    test()->actingAs($admin)->postJson('/stock/movements', ['medicine_id' => $m->id, 'type' => 'out', 'quantity' => 6, 'reason' => 'Dijual'])
        ->assertStatus(409)->assertJsonPath('code', 'stock.insufficient');

    tenant()->set($klinik->id);
    expect(saldo($m))->toBe(5);
});

it('OBT-05 Admin dapat override dengan alasan: kekurangan dicatat sebagai penyesuaian', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $m = makeMedicine($klinik);
    isiStok($klinik, $m, 5);

    test()->actingAs($admin)->post('/stock/movements', ['medicine_id' => $m->id, 'type' => 'out', 'quantity' => 8, 'reason' => 'Dijual', 'override_reason' => 'Stok fisik ternyata ada, belum tercatat'])->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    expect(saldo($m))->toBe(0);
    $adj = StockMovementModel::query()->where('type', 'adjustment')->firstOrFail();
    expect($adj->quantity)->toBe(3)->and($adj->reason)->toContain('Override')
        ->and(AuditLogModel::query()->where('action', 'stock.override')->exists())->toBeTrue();
});

it('OBT-05 perawat tidak dapat override walau mengirim alasan', function () {
    $klinik = makeClinic();
    $perawat = makeUser(Role::Perawat, $klinik);
    $m = makeMedicine($klinik);
    isiStok($klinik, $m, 2);

    test()->actingAs($perawat)->postJson('/stock/movements', ['medicine_id' => $m->id, 'type' => 'out', 'quantity' => 5, 'reason' => 'Dipakai', 'override_reason' => 'Paksa'])
        ->assertStatus(409)->assertJsonPath('code', 'stock.insufficient');
});

it('OBT-05 penyesuaian (opname) menetapkan jumlah fisik dengan alasan wajib dan selisih tercatat', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $m = makeMedicine($klinik);
    isiStok($klinik, $m, 20);

    test()->actingAs($admin)->post('/stock/adjustments', ['medicine_id' => $m->id, 'counted_quantity' => 17, 'reason' => ''])->assertSessionHasErrors('reason');
    test()->actingAs($admin)->post('/stock/adjustments', ['medicine_id' => $m->id, 'counted_quantity' => 17, 'reason' => 'Opname bulanan'])->assertSessionHasNoErrors();
    test()->actingAs($admin)->post('/stock/adjustments', ['medicine_id' => $m->id, 'counted_quantity' => 17, 'reason' => 'Opname ulang'])->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    expect(saldo($m))->toBe(17)->and(StockMovementModel::query()->where('type', 'adjustment')->count())->toBe(1);
    $adj = StockMovementModel::query()->where('type', 'adjustment')->firstOrFail();
    expect($adj->quantity)->toBe(-3)->and($adj->reason)->toBe('Opname bulanan');
    $log = AuditLogModel::query()->where('action', 'stock.adjusted')->firstOrFail();
    expect($log->before['quantity'])->toBe(20)->and($log->after['quantity'])->toBe(17);
});

it('OBT-05 jumlah, obat, dan tanggal kedaluwarsa divalidasi', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $m = makeMedicine($klinik);
    $nonaktif = makeMedicine($klinik, ['is_active' => false]);
    $kirim = fn (array $d) => test()->actingAs($admin)->post('/stock/movements', $d + ['type' => 'in']);

    $kirim(['medicine_id' => $m->id, 'quantity' => 0])->assertSessionHasErrors('quantity');
    $kirim(['medicine_id' => $m->id, 'quantity' => -3])->assertSessionHasErrors('quantity');
    $kirim(['medicine_id' => $m->id, 'quantity' => 5, 'expiry_date' => '31/12/2026'])->assertSessionHasErrors('expiry_date');
    $kirim(['medicine_id' => $nonaktif->id, 'quantity' => 5])->assertSessionHasErrors('domain');
    $kirim(['medicine_id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ', 'quantity' => 5])->assertSessionHasErrors('domain');
    test()->actingAs($admin)->post('/stock/movements', ['medicine_id' => $m->id, 'type' => 'adjustment', 'quantity' => 5])->assertSessionHasErrors('type');
});

it('OBT-05 mutasi stok tidak dapat diubah atau dihapus', function () {
    $klinik = makeClinic();
    $m = makeMedicine($klinik);
    isiStok($klinik, $m, 5);
    tenant()->set($klinik->id);
    $mut = StockMovementModel::query()->firstOrFail();

    expect(fn () => $mut->update(['quantity' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $mut->delete())->toThrow(LogicException::class);
});

it('OBT-05 halaman stok menampilkan saldo dan stok minimum; riwayat mutasi ada di halaman sendiri', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $m = makeMedicine($klinik, ['min_stock' => 10]);
    isiStok($klinik, $m, 4);

    test()->actingAs($admin)->get('/stock')->assertOk()->assertInertia(fn ($p) => $p
        ->component('Stock/Index')->where('medicines.0.quantity', 4)->where('medicines.0.min_stock', 10)->where('can.manage', true));
    test()->actingAs($admin)->get("/stock/{$m->id}/history")->assertOk()->assertInertia(fn ($p) => $p
        ->component('Stock/History')->where('medicine.quantity', 4)
        ->has('movements', 1)->where('movements.0.type_label', 'Stok masuk')->where('can.manage', true));
});

it('OBT-05 formulir mutasi dan penyesuaian stok adalah halaman sendiri dan hanya untuk pengelola stok', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $m = makeMedicine($klinik);

    test()->actingAs($admin)->get("/stock/movements/create?medicine={$m->id}&type=out")->assertOk()->assertInertia(fn ($p) => $p
        ->component('Stock/MovementForm')->where('preset.medicine', $m->id)->where('preset.type', 'out')->where('can.override', true));
    test()->actingAs($admin)->get('/stock/adjustments/create')->assertOk()->assertInertia(fn ($p) => $p->component('Stock/AdjustmentForm'));
    test()->actingAs(makeUser(Role::Dokter, $klinik))->get('/stock/movements/create')->assertForbidden();
    test()->actingAs(makeUser(Role::Dokter, $klinik))->get('/stock/adjustments/create')->assertForbidden();
});

it('OBT-05 setelah menyimpan, pengguna diarahkan ke riwayat mutasi obat itu', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $m = makeMedicine($klinik);

    test()->actingAs($admin)->post('/stock/movements', ['medicine_id' => $m->id, 'type' => 'in', 'quantity' => 5])->assertRedirect("/stock/{$m->id}/history");
    test()->actingAs($admin)->post('/stock/adjustments', ['medicine_id' => $m->id, 'counted_quantity' => 3, 'reason' => 'Opname'])->assertRedirect("/stock/{$m->id}/history");
});

it('OBT-05 riwayat obat klinik lain tidak dapat dibuka', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $m = makeMedicine($b);

    test()->actingAs(makeUser(Role::Admin, $a))->get("/stock/{$m->id}/history")->assertNotFound();
});

it('OBT-05 pencarian obat mengembalikan stok tersedia, hanya obat aktif klinik sendiri', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $m = makeMedicine($a, ['name' => 'Amoksisilin 500 mg']);
    makeMedicine($a, ['name' => 'Amoksisilin lama', 'is_active' => false]);
    makeMedicine($b, ['name' => 'Amoksisilin B']);
    isiStok($a, $m, 30);

    $hasil = test()->actingAs(makeUser(Role::Dokter, $a))->getJson('/internal-api/medicines/search?q=amoks')->assertOk()->json('data');

    expect($hasil)->toHaveCount(1)->and($hasil[0]['name'])->toBe('Amoksisilin 500 mg')->and($hasil[0]['available'])->toBe(30)->and($hasil[0]['sell_price_amount'])->toBe(5000);
    test()->actingAs(makeUser(Role::Kasir, $a))->getJson('/internal-api/medicines/search?q=amoks')->assertForbidden();
});

it('OBT-05 izin stok: dokter hanya melihat, perawat mengelola, kasir dan resepsionis ditolak', function () {
    $klinik = makeClinic();
    $m = makeMedicine($klinik);
    $kirim = ['medicine_id' => $m->id, 'type' => 'in', 'quantity' => 5];

    test()->actingAs(makeUser(Role::Dokter, $klinik))->get('/stock')->assertOk();
    test()->actingAs(makeUser(Role::Dokter, $klinik))->post('/stock/movements', $kirim)->assertForbidden();
    test()->actingAs(makeUser(Role::Perawat, $klinik))->post('/stock/movements', $kirim)->assertSessionHasNoErrors();
    test()->actingAs(makeUser(Role::Kasir, $klinik))->get('/stock')->assertForbidden();
    test()->actingAs(makeUser(Role::Resepsionis, $klinik))->get('/stock')->assertForbidden();
});

it('OBT-05 stok obat klinik lain tidak dapat disentuh', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $m = makeMedicine($a);

    test()->actingAs(makeUser(Role::Admin, $b))->post('/stock/movements', ['medicine_id' => $m->id, 'type' => 'in', 'quantity' => 5])->assertSessionHasErrors('domain');
    test()->actingAs(makeUser(Role::Admin, $b))->post('/stock/adjustments', ['medicine_id' => $m->id, 'counted_quantity' => 5, 'reason' => 'x'])->assertSessionHasErrors('domain');
});

// --- OBT-01, OBT-02, OBT-04: resep ---

it('OBT-01 dokter membuat resep dari obat katalog dan teks bebas tanpa mengubah stok', function () {
    Event::fake([MedicineDispensed::class]);
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik'], ['name' => 'Amoksisilin 500 mg']);
    isiStok($s['klinik'], $m, 50);

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [
        barisResep($m),
        ['free_text_name' => 'Obat kumur herbal', 'dose' => '10 ml', 'frequency' => '2 x sehari', 'duration' => '7 hari', 'quantity' => 1],
    ]])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $resep = PrescriptionModel::query()->firstOrFail();
    $item = PrescriptionItemModel::query()->orderBy('medicine_name')->get();
    expect($resep->doctor_id)->toBe($s['dokter']->id)->and($resep->patient_id)->toBe($s['pasien'])->and($resep->status)->toBe('issued')
        ->and($item)->toHaveCount(2)->and($item[0]->medicine_name)->toBe('Amoksisilin 500 mg')->and($item[1]->free_text_name)->toBe('Obat kumur herbal')
        ->and(saldo($m))->toBe(50);
    Event::assertNotDispatched(MedicineDispensed::class);
});

it('OBT-01 baris resep wajib lengkap dan sah', function () {
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik']);
    $kirim = fn (array $items) => test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => $items]);

    $kirim([])->assertSessionHasErrors('items');
    $kirim([barisResep($m, ['dose' => ''])])->assertSessionHasErrors('items.0.dose');
    $kirim([barisResep($m, ['quantity' => 0])])->assertSessionHasErrors('items.0.quantity');
    $kirim([barisResep($m, ['free_text_name' => 'Obat X'])])->assertSessionHasErrors('domain');
    $kirim([['dose' => '1', 'frequency' => '1', 'duration' => '1', 'quantity' => 1]])->assertSessionHasErrors('domain');
    $kirim([['free_text_name' => 'Obat bebas', 'dose' => '1', 'frequency' => '1', 'duration' => '1', 'quantity' => 1, 'dispense_in_clinic' => true]])->assertSessionHasErrors('domain');

    tenant()->set($s['klinik']->id);
    expect(PrescriptionModel::query()->count())->toBe(0);
});

it('OBT-04 obat yang dijual di klinik mengurangi stok dan memicu event dengan salinan harga jual', function () {
    Event::fake([MedicineDispensed::class]);
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik'], ['sell_price_amount' => 7500]);
    isiStok($s['klinik'], $m, 20);

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [barisResep($m, ['quantity' => 6, 'dispense_in_clinic' => true])]])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $item = PrescriptionItemModel::query()->firstOrFail();
    expect(saldo($m))->toBe(14)->and($item->unit_price_amount)->toBe(7500)->and($item->dispense_in_clinic)->toBeTrue();
    $keluar = StockMovementModel::query()->where('type', 'out')->firstOrFail();
    expect($keluar->quantity)->toBe(-6)->and($keluar->reference_type)->toBe('prescription_item')->and($keluar->reference_id)->toBe($item->id);
    Event::assertDispatched(MedicineDispensed::class, fn ($e) => $e->quantity === 6 && $e->unitPriceAmount === 7500 && $e->prescriptionItemId === $item->id && $e->patientId === $s['pasien']);
});

it('OBT-04 mengubah harga jual obat sesudahnya tidak mengubah harga pada resep yang sudah dijual', function () {
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik'], ['sell_price_amount' => 5000]);
    isiStok($s['klinik'], $m, 10);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [barisResep($m, ['quantity' => 2, 'dispense_in_clinic' => true])]]);

    tenant()->set($s['klinik']->id);
    MedicineModel::query()->whereKey($m->id)->update(['sell_price_amount' => 99000]);

    expect(PrescriptionItemModel::query()->firstOrFail()->unit_price_amount)->toBe(5000);
});

it('OBT-04 stok kurang membatalkan seluruh resep, tidak ada yang tersimpan', function () {
    $s = siapKunjungan();
    $cukup = makeMedicine($s['klinik']);
    $kurang = makeMedicine($s['klinik']);
    isiStok($s['klinik'], $cukup, 10);
    isiStok($s['klinik'], $kurang, 1);

    test()->actingAs($s['dokter_user'])->postJson("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [
        barisResep($cukup, ['quantity' => 3, 'dispense_in_clinic' => true]),
        barisResep($kurang, ['quantity' => 5, 'dispense_in_clinic' => true]),
    ]])->assertStatus(409)->assertJsonPath('code', 'stock.insufficient');

    tenant()->set($s['klinik']->id);
    expect(PrescriptionModel::query()->count())->toBe(0)->and(PrescriptionItemModel::query()->count())->toBe(0)->and(saldo($cukup))->toBe(10)->and(saldo($kurang))->toBe(1);
});

it('OBT-04 override stok hanya berlaku bagi Admin', function () {
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik']);
    isiStok($s['klinik'], $m, 1);
    $payload = ['items' => [barisResep($m, ['quantity' => 3, 'dispense_in_clinic' => true])], 'stock_override_reason' => 'Stok fisik ada'];

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", $payload)->assertSessionHasErrors('domain');
    tenant()->set($s['klinik']->id);
    expect(saldo($m))->toBe(1);
});

it('OBT-02 resep dengan kelas obat yang cocok dengan alergi pasien ditahan sampai dikonfirmasi', function () {
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik'], ['name' => 'Amoksisilin', 'drug_class' => 'penisilin']);
    tenant()->set($s['klinik']->id);
    (new PatientMedicalHistoryModel)->forceFill(['patient_id' => $s['pasien'], 'type' => 'allergy', 'name' => 'Penisilin', 'severity' => 'severe'])->save();

    test()->actingAs($s['dokter_user'])->postJson("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [barisResep($m)]])
        ->assertStatus(409)->assertJsonPath('code', 'prescription.allergy_warning');
    tenant()->set($s['klinik']->id);
    expect(PrescriptionModel::query()->count())->toBe(0);

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [barisResep($m)], 'allergy_acknowledgement' => 'Reaksi lama ringan, pasien dipantau'])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    expect(PrescriptionModel::query()->firstOrFail()->allergy_acknowledged)->toBe('Reaksi lama ringan, pasien dipantau');
});

it('OBT-02 obat dengan kelas lain tidak memicu peringatan', function () {
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik'], ['drug_class' => 'nsaid']);
    tenant()->set($s['klinik']->id);
    (new PatientMedicalHistoryModel)->forceFill(['patient_id' => $s['pasien'], 'type' => 'allergy', 'name' => 'Penisilin'])->save();

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [barisResep($m)]])->assertSessionHasNoErrors();
});

it('OBT-01 resep tidak dapat dibuat pada kunjungan yang sudah ditutup', function () {
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik']);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [barisResep($m)]])->assertSessionHasErrors('domain');
});

it('OBT-04 membatalkan resep mengembalikan stok dan memicu event pelepasan tagihan', function () {
    Event::fake([MedicineReturned::class]);
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik']);
    isiStok($s['klinik'], $m, 10);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [barisResep($m, ['quantity' => 4, 'dispense_in_clinic' => true])]]);
    tenant()->set($s['klinik']->id);
    $resep = PrescriptionModel::query()->firstOrFail();
    expect(saldo($m))->toBe(6);

    test()->actingAs($s['dokter_user'])->post("/prescriptions/{$resep->id}/void", ['reason' => ''])->assertSessionHasErrors('reason');
    test()->actingAs($s['dokter_user'])->post("/prescriptions/{$resep->id}/void", ['reason' => 'Salah resep'])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    expect(saldo($m))->toBe(10)->and($resep->fresh()->status)->toBe('void')->and($resep->fresh()->void_reason)->toBe('Salah resep');
    Event::assertDispatched(MedicineReturned::class);
    test()->actingAs($s['dokter_user'])->post("/prescriptions/{$resep->id}/void", ['reason' => 'lagi'])->assertSessionHasErrors('domain');
});

it('OBT-04 resep pada kunjungan tertutup tidak dapat dibatalkan', function () {
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik']);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [barisResep($m)]]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close");
    tenant()->set($s['klinik']->id);
    $resep = PrescriptionModel::query()->firstOrFail();

    test()->actingAs($s['dokter_user'])->post("/prescriptions/{$resep->id}/void", ['reason' => 'Terlambat'])->assertSessionHasErrors('domain');
});

it('OBT-03 resep dicetak sebagai PDF dengan kop klinik, tercatat di access log dan audit', function () {
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik']);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [barisResep($m)]]);
    tenant()->set($s['klinik']->id);
    $resep = PrescriptionModel::query()->firstOrFail();

    $respons = test()->actingAs($s['perawat'])->get("/prescriptions/{$resep->id}/print")->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect(str_starts_with((string) $respons->getContent(), '%PDF'))->toBeTrue();
    expect(DB::table('access_logs')->where('patient_id', $s['pasien'])->where('actor_id', $s['perawat']->id)->exists())->toBeTrue();
    tenant()->set($s['klinik']->id);
    expect(AuditLogModel::query()->where('action', 'prescription.printed')->exists())->toBeTrue();
});

it('OBT izin resep: perawat melihat tetapi tidak membuat; resepsionis tidak melihat; klinik lain ditolak', function () {
    $a = siapKunjungan();
    $m = makeMedicine($a['klinik']);
    test()->actingAs($a['dokter_user'])->post("/encounters/{$a['kunjungan']}/prescriptions", ['items' => [barisResep($m)]]);
    tenant()->set($a['klinik']->id);
    $resep = PrescriptionModel::query()->firstOrFail();
    $b = siapKunjungan();

    test()->actingAs($a['perawat'])->post("/encounters/{$a['kunjungan']}/prescriptions", ['items' => [barisResep($m)]])->assertForbidden();
    test()->actingAs($a['perawat'])->post("/prescriptions/{$resep->id}/void", ['reason' => 'x'])->assertForbidden();
    test()->actingAs($a['user'])->get("/prescriptions/{$resep->id}/print")->assertForbidden();
    test()->actingAs($b['dokter_user'])->get("/prescriptions/{$resep->id}/print")->assertSessionHasErrors('domain');
    test()->actingAs($b['dokter_user'])->post("/prescriptions/{$resep->id}/void", ['reason' => 'x'])->assertSessionHasErrors('domain');
    test()->actingAs($b['dokter_user'])->post("/encounters/{$a['kunjungan']}/prescriptions", ['items' => [barisResep($m)]])->assertSessionHasErrors('domain');
});

it('OBT-01 formulir resep adalah halaman sendiri; simpan kembali ke tab resep kunjungan', function () {
    $s = siapKunjungan();
    $m = makeMedicine($s['klinik']);

    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}/prescriptions/create")->assertOk()->assertInertia(fn ($p) => $p
        ->component('Encounters/PrescriptionForm')->where('encounter.id', $s['kunjungan'])->where('can.overrideStock', false));
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [barisResep($m)]])
        ->assertRedirect("/encounters/{$s['kunjungan']}?tab=prescriptions");
});

it('OBT-01 formulir resep ditolak untuk yang tidak berhak dan setelah kunjungan ditutup', function () {
    $s = siapKunjungan();
    test()->actingAs(makeUser(Role::Kasir, $s['klinik']))->get("/encounters/{$s['kunjungan']}/prescriptions/create")->assertForbidden();

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close");
    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}/prescriptions/create")->assertRedirect("/encounters/{$s['kunjungan']}?tab=prescriptions");
});
