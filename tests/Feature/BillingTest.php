<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Billing\Infrastructure\Eloquent\CashClosingModel;
use Src\Billing\Infrastructure\Eloquent\InvoiceItemModel;
use Src\Billing\Infrastructure\Eloquent\InvoiceModel;
use Src\Billing\Infrastructure\Eloquent\PaymentModel;
use Src\Clinic\Infrastructure\Eloquent\PaymentMethodModel;
use Src\Clinical\Infrastructure\Eloquent\EncounterProcedureModel;
use Src\IdentityAccess\Domain\Role;
use Src\Pharmacy\Infrastructure\Eloquent\PrescriptionItemModel;
use Src\Pharmacy\Infrastructure\Eloquent\PrescriptionModel;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function metodeBayar(array $s, string $nama = 'Tunai', string $jenis = 'cash'): PaymentMethodModel
{
    tenant()->set($s['klinik']->id);
    $m = new PaymentMethodModel;
    $m->forceFill(['name' => $nama, 'type' => $jenis, 'is_active' => true])->save();

    return $m;
}

/**
 * Kunjungan berjalan dengan satu tindakan (Rp 250.000 x 2) sehingga draft tagihan terbentuk.
 *
 * @return array<string, mixed>
 */
function siapTagihan(array $tindakanOpsi = []): array
{
    $s = siapKunjungan();
    $s['kasir'] = makeUser(Role::Kasir, $s['klinik']);
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    $s['pemilik'] = makeUser(Role::Pemilik, $s['klinik']);
    $s['tunai'] = metodeBayar($s, 'Tunai', 'cash');
    $s['qris'] = metodeBayar($s, 'QRIS Klinik', 'qris');
    $s['tindakan'] = tindakan($s, ['default_price_amount' => 250000, ...$tindakanOpsi]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $s['tindakan']->id, 'tooth_fdi' => 46, 'quantity' => 2])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $s['invoice'] = InvoiceModel::query()->where('encounter_id', $s['kunjungan'])->firstOrFail()->id;

    return $s;
}

function tutupKunjungan(array $s): void
{
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
}

/** Tagihan terbit (kunjungan ditutup) siap dibayar. */
function tagihanTerbit(array $tindakanOpsi = []): array
{
    $s = siapTagihan($tindakanOpsi);
    tutupKunjungan($s);

    return $s;
}

function bayarTagihan(array $s, int $jumlah, ?object $metode = null, string $jenis = 'pay', ?string $kunci = null, ?object $user = null)
{
    $r = test()->actingAs($user ?? $s['kasir'])->post("/invoices/{$s['invoice']}/payments", [
        'payment_method_id' => ($metode ?? $s['tunai'])->id, 'type' => $jenis, 'amount' => $jumlah,
    ], ['Idempotency-Key' => $kunci ?? 'key-'.uniqid()]);
    tenant()->set($s['klinik']->id);

    return $r;
}

function tagihanModel(array $s): InvoiceModel
{
    tenant()->set($s['klinik']->id);

    return InvoiceModel::query()->findOrFail($s['invoice']);
}

// --- MST-08 dan KSR-01: tagihan otomatis dengan tarif tersalin ---

it('KSR-01 tindakan pada kunjungan membuat draft tagihan dengan item dan total dari salinan tarif', function () {
    $s = siapTagihan();

    $invoice = tagihanModel($s);
    $item = InvoiceItemModel::query()->where('invoice_id', $invoice->id)->firstOrFail();
    expect($invoice->lifecycle)->toBe('draft')->and($invoice->status)->toBe('draft')->and($invoice->number)->toBeNull()
        ->and($invoice->total_amount)->toBe(500000)->and($invoice->patient_id)->toBe($s['pasien'])
        ->and($item->source_type)->toBe('procedure')->and($item->quantity)->toBe(2)->and($item->unit_price_amount)->toBe(250000)->and($item->total_amount)->toBe(500000)
        ->and(AuditLogModel::query()->where('action', 'invoice.item_added')->exists())->toBeTrue();
});

it('MST-08 perubahan tarif di katalog tidak mengubah item tagihan yang sudah ada', function () {
    $s = siapTagihan();

    $s['tindakan']->forceFill(['default_price_amount' => 900000])->save();
    $kedua = tindakan($s, ['default_price_amount' => 100000]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $kedua->id, 'tooth_fdi' => 11, 'quantity' => 1]);

    tenant()->set($s['klinik']->id);
    $harga = InvoiceItemModel::query()->where('invoice_id', $s['invoice'])->orderBy('created_at')->pluck('unit_price_amount')->all();
    expect($harga)->toBe([250000, 100000])->and(tagihanModel($s)->total_amount)->toBe(600000);
});

it('KSR-01 tindakan yang dibatalkan dari kunjungan melepas itemnya dari draft tagihan', function () {
    $s = siapTagihan();
    tenant()->set($s['klinik']->id);
    $entry = EncounterProcedureModel::query()->firstOrFail()->id;

    test()->actingAs($s['dokter_user'])->delete("/encounters/{$s['kunjungan']}/procedures/{$entry}")->assertRedirect();

    tenant()->set($s['klinik']->id);
    expect(InvoiceItemModel::query()->where('invoice_id', $s['invoice'])->count())->toBe(0)
        ->and(InvoiceItemModel::withTrashed()->where('invoice_id', $s['invoice'])->count())->toBe(1)
        ->and(tagihanModel($s)->total_amount)->toBe(0);
});

it('KSR-01 obat yang dijual di klinik masuk tagihan dengan harga jual tersalin dan dilepas bila resep dibatalkan', function () {
    $s = siapTagihan();
    $obat = makeMedicine($s['klinik'], ['sell_price_amount' => 7500]);
    isiStok($s['klinik'], $obat, 50);

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/prescriptions", ['items' => [
        ['medicine_id' => $obat->id, 'dose' => '500 mg', 'frequency' => '3 x sehari', 'duration' => '5 hari', 'quantity' => 6, 'dispense_in_clinic' => true],
    ]])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $baris = InvoiceItemModel::query()->where('invoice_id', $s['invoice'])->where('source_type', 'medicine')->firstOrFail();
    expect($baris->quantity)->toBe(6)->and($baris->unit_price_amount)->toBe(7500)->and($baris->total_amount)->toBe(45000)
        ->and(tagihanModel($s)->total_amount)->toBe(545000);

    $resep = PrescriptionModel::query()->firstOrFail();
    test()->actingAs($s['dokter_user'])->post("/prescriptions/{$resep->id}/void", ['reason' => 'Salah obat'])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    expect(InvoiceItemModel::query()->where('invoice_id', $s['invoice'])->where('source_type', 'medicine')->count())->toBe(0)
        ->and(tagihanModel($s)->total_amount)->toBe(500000)
        ->and(PrescriptionItemModel::query()->count())->toBe(1);
});

it('KSR-01 menutup kunjungan menerbitkan tagihan dengan nomor sesuai format klinik', function () {
    $s = siapTagihan();
    tutupKunjungan($s);

    $invoice = tagihanModel($s);
    expect($invoice->lifecycle)->toBe('issued')->and($invoice->status)->toBe('unpaid')->and($invoice->number)->toBe('INV-2025-00001')
        ->and($invoice->issued_at)->not->toBeNull()
        ->and(AuditLogModel::query()->where('action', 'invoice.issued')->exists())->toBeTrue();
});

it('KSR-01 kunjungan tanpa tindakan atau obat tidak menghasilkan tagihan', function () {
    $s = siapKunjungan();
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'Kontrol']);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    expect(InvoiceModel::query()->count())->toBe(0);
});

it('KSR-01 kasir menambah, mengubah jumlah, dan menghapus item manual; item klinis tidak dapat diubah', function () {
    $s = tagihanTerbit();

    test()->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/items", ['description' => 'Biaya administrasi', 'quantity' => 1, 'unit_price_amount' => 20000])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $manual = InvoiceItemModel::query()->where('source_type', 'manual')->firstOrFail();
    expect(tagihanModel($s)->total_amount)->toBe(520000);

    test()->actingAs($s['kasir'])->put("/invoices/{$s['invoice']}/items/{$manual->id}", ['quantity' => 3])->assertSessionHasNoErrors();
    expect(tagihanModel($s)->total_amount)->toBe(560000);

    $klinis = InvoiceItemModel::query()->where('source_type', 'procedure')->firstOrFail();
    test()->actingAs($s['kasir'])->putJson("/invoices/{$s['invoice']}/items/{$klinis->id}", ['quantity' => 1])->assertStatus(409)->assertJsonPath('code', 'invoice.not_editable');
    test()->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/items/{$manual->id}/remove")->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    expect(tagihanModel($s)->total_amount)->toBe(500000)->and(InvoiceItemModel::withTrashed()->where('source_type', 'manual')->count())->toBe(1);
});

it('KSR-01 item tidak dapat diubah setelah ada pembayaran dan harga satuan tidak boleh desimal', function () {
    $s = tagihanTerbit();
    test()->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/items", ['description' => 'Desimal', 'quantity' => 1, 'unit_price_amount' => '1500.50'])->assertSessionHasErrors('unit_price_amount');

    bayarTagihan($s, 10000)->assertSessionHasNoErrors();
    test()->actingAs($s['kasir'])->postJson("/invoices/{$s['invoice']}/items", ['description' => 'Terlambat', 'quantity' => 1, 'unit_price_amount' => 1000])->assertStatus(409)->assertJsonPath('code', 'invoice.not_editable');
});

// --- KSR-02: diskon ---

it('KSR-02 diskon per item dan per tagihan wajib beralasan dan menurunkan total', function () {
    $s = tagihanTerbit();
    tenant()->set($s['klinik']->id);
    $item = InvoiceItemModel::query()->firstOrFail();

    test()->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/items/{$item->id}/discount", ['kind' => 'percent', 'value' => '10', 'reason' => ''])->assertSessionHasErrors('reason');
    test()->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/items/{$item->id}/discount", ['kind' => 'percent', 'value' => '10', 'reason' => 'Pasien lama'])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    expect(InvoiceItemModel::query()->findOrFail($item->id)->discount_amount)->toBe(50000)->and(tagihanModel($s)->total_amount)->toBe(450000);

    test()->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/discount", ['kind' => 'amount', 'value' => '5000', 'reason' => 'Promo'])->assertSessionHasNoErrors();
    $invoice = tagihanModel($s);
    expect($invoice->discount_amount)->toBe(5000)->and($invoice->total_amount)->toBe(445000)->and($invoice->discount_reason)->toBe('Promo')
        ->and(AuditLogModel::query()->where('action', 'invoice.discount_applied')->exists())->toBeTrue();

    test()->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/discount", ['kind' => 'none'])->assertSessionHasNoErrors();
    expect(tagihanModel($s)->total_amount)->toBe(450000);
});

it('KSR-02 diskon di atas batas peran ditolak; batas dapat diatur Admin per peran', function () {
    $s = tagihanTerbit();
    $diskon = fn (string $nilai) => test()->actingAs($s['kasir'])->postJson("/invoices/{$s['invoice']}/discount", ['kind' => 'percent', 'value' => $nilai, 'reason' => 'Uji batas']);

    $diskon('10.01')->assertStatus(422)->assertJsonPath('code', 'discount.exceeds_limit');
    $diskon('10')->assertRedirect();

    $jam = ['open' => '08:00', 'close' => '17:00'];
    test()->actingAs($s['admin'])->put('/settings/clinic', [
        'timezone' => 'Asia/Jakarta', 'session_timeout_minutes' => 30, 'medical_record_pattern' => 'RM-{seq:6}', 'invoice_pattern' => 'INV-{yyyy}-{seq:5}',
        'discount_limits' => ['pemilik' => 100, 'admin' => 100, 'kasir' => 25],
        'operating_hours' => [1 => $jam, 2 => $jam, 3 => $jam, 4 => $jam, 5 => $jam, 6 => $jam, 7 => null],
    ])->assertSessionHasNoErrors();
    $diskon('25')->assertRedirect();
    $diskon('25,01')->assertStatus(422);
    test()->actingAs($s['kasir'])->postJson("/invoices/{$s['invoice']}/discount", ['kind' => 'amount', 'value' => '999999999', 'reason' => 'Terlalu besar'])->assertStatus(422)->assertJsonPath('code', 'discount.exceeds_limit');
});

it('KSR-02 diskon ditolak setelah ada pembayaran dan oleh peran tanpa izin', function () {
    $s = tagihanTerbit();
    test()->actingAs($s['admin'])->post("/invoices/{$s['invoice']}/discount", ['kind' => 'amount', 'value' => '1000', 'reason' => 'x'])->assertForbidden();

    bayarTagihan($s, 1000);
    test()->actingAs($s['kasir'])->postJson("/invoices/{$s['invoice']}/discount", ['kind' => 'amount', 'value' => '1000', 'reason' => 'Terlambat'])->assertStatus(409)->assertJsonPath('code', 'invoice.not_editable');
});

// --- KSR-03: pembayaran ---

it('KSR-03 pembayaran campuran tunai dan QRIS melunasi tagihan dan kwitansi bernomor urut', function () {
    $s = tagihanTerbit();

    bayarTagihan($s, 200000, $s['tunai'])->assertSessionHasNoErrors();
    expect(tagihanModel($s)->status)->toBe('partial');
    bayarTagihan($s, 300000, $s['qris'])->assertSessionHasNoErrors();

    $invoice = tagihanModel($s);
    $pembayaran = PaymentModel::query()->orderBy('paid_at')->orderBy('id')->get();
    expect($invoice->status)->toBe('paid')->and($invoice->paid_amount)->toBe(500000)
        ->and($pembayaran->pluck('receipt_no')->all())->toBe(['KW-2025-00001', 'KW-2025-00002'])
        ->and($pembayaran->pluck('received_by')->unique()->all())->toBe([$s['kasir']->id])
        ->and(AuditLogModel::query()->where('action', 'payment.recorded')->count())->toBe(2);

    test()->actingAs($s['kasir'])->postJson("/invoices/{$s['invoice']}/payments", ['payment_method_id' => $s['tunai']->id, 'type' => 'pay', 'amount' => 1], ['Idempotency-Key' => 'key-lunas-1'])
        ->assertStatus(409)->assertJsonPath('code', 'invoice.already_paid');
});

it('M7 pembayaran memakai Idempotency-Key: kirim ulang tidak mencatat dua kali dan isi berbeda ditolak', function () {
    $s = tagihanTerbit();

    bayarTagihan($s, 100000, null, 'pay', 'kunci-ganda-01')->assertSessionHasNoErrors();
    bayarTagihan($s, 100000, null, 'pay', 'kunci-ganda-01')->assertSessionHasNoErrors();
    expect(PaymentModel::query()->count())->toBe(1)->and(tagihanModel($s)->paid_amount)->toBe(100000);

    test()->actingAs($s['kasir'])->postJson("/invoices/{$s['invoice']}/payments", ['payment_method_id' => $s['tunai']->id, 'type' => 'pay', 'amount' => 90000], ['Idempotency-Key' => 'kunci-ganda-01'])
        ->assertStatus(409)->assertJsonPath('code', 'payment.idempotency_conflict');
    tenant()->set($s['klinik']->id);
    expect(PaymentModel::query()->count())->toBe(1);
});

it('M7 pembayaran tanpa Idempotency-Key ditolak', function () {
    $s = tagihanTerbit();

    test()->actingAs($s['kasir'])->postJson("/invoices/{$s['invoice']}/payments", ['payment_method_id' => $s['tunai']->id, 'type' => 'pay', 'amount' => 1000])
        ->assertStatus(422)->assertJsonPath('code', 'payment.idempotency_key_required');
    expect(PaymentModel::query()->count())->toBe(0);
});

it('KSR-03 jumlah melebihi sisa, desimal, metode nonaktif, dan tagihan draft ditolak', function () {
    $s = tagihanTerbit();

    test()->actingAs($s['kasir'])->postJson("/invoices/{$s['invoice']}/payments", ['payment_method_id' => $s['tunai']->id, 'type' => 'pay', 'amount' => 500001], ['Idempotency-Key' => 'key-lebih-1'])
        ->assertStatus(409)->assertJsonPath('code', 'invoice.overpayment');
    test()->actingAs($s['kasir'])->postJson("/invoices/{$s['invoice']}/payments", ['payment_method_id' => $s['tunai']->id, 'type' => 'pay', 'amount' => '1000.5'], ['Idempotency-Key' => 'key-desimal-1'])
        ->assertStatus(422);

    $s['qris']->forceFill(['is_active' => false])->save();
    test()->actingAs($s['kasir'])->postJson("/invoices/{$s['invoice']}/payments", ['payment_method_id' => $s['qris']->id, 'type' => 'pay', 'amount' => 1000], ['Idempotency-Key' => 'key-nonaktif-1'])
        ->assertStatus(422)->assertJsonPath('code', 'payment.method_not_found');

    $draft = siapTagihan();
    bayarTagihan($draft, 1000, null, 'pay', 'key-draft-0001')->assertSessionHasErrors('domain');
});

it('KSR-04 uang muka pada draft lalu pelunasan setelah terbit menyisakan piutang sampai lunas', function () {
    $s = siapTagihan();

    bayarTagihan($s, 150000, $s['tunai'], 'dp')->assertSessionHasNoErrors();
    expect(tagihanModel($s)->status)->toBe('draft')->and(tagihanModel($s)->paid_amount)->toBe(150000);

    tutupKunjungan($s);
    $invoice = tagihanModel($s);
    expect($invoice->status)->toBe('partial')->and($invoice->total_amount - $invoice->paid_amount)->toBe(350000);

    bayarTagihan($s, 100000, $s['qris'])->assertSessionHasNoErrors();
    expect(tagihanModel($s)->status)->toBe('partial');
    bayarTagihan($s, 250000, $s['tunai'])->assertSessionHasNoErrors();
    expect(tagihanModel($s)->status)->toBe('paid');
    expect(PaymentModel::query()->where('type', 'dp')->count())->toBe(1);
});

it('KSR-03 hanya kasir yang boleh mencatat pembayaran', function () {
    $s = tagihanTerbit();

    foreach (['admin', 'pemilik', 'dokter_user'] as $peran) {
        bayarTagihan($s, 1000, null, 'pay', 'key-'.$peran.'-001', $s[$peran])->assertForbidden();
    }
    expect(PaymentModel::query()->count())->toBe(0);
});

// --- KSR-07: refund dan void ---

it('KSR-07 refund sebagian dicatat sebagai transaksi pembalik tanpa menghapus pembayaran', function () {
    $s = tagihanTerbit();
    bayarTagihan($s, 500000, $s['tunai']);

    test()->actingAs($s['admin'])->post("/invoices/{$s['invoice']}/refunds", ['payment_method_id' => $s['tunai']->id, 'amount' => 120000, 'reason' => ''], ['Idempotency-Key' => 'refund-0001'])->assertSessionHasErrors('reason');
    test()->actingAs($s['admin'])->post("/invoices/{$s['invoice']}/refunds", ['payment_method_id' => $s['tunai']->id, 'amount' => 120000, 'reason' => 'Tindakan dibatalkan'], ['Idempotency-Key' => 'refund-0002'])->assertSessionHasNoErrors();

    $invoice = tagihanModel($s);
    expect($invoice->paid_amount)->toBe(380000)->and($invoice->status)->toBe('partial')
        ->and(PaymentModel::query()->count())->toBe(2)->and(PaymentModel::query()->where('type', 'refund')->value('amount'))->toBe(120000);

    test()->actingAs($s['admin'])->postJson("/invoices/{$s['invoice']}/refunds", ['payment_method_id' => $s['tunai']->id, 'amount' => 380001, 'reason' => 'Terlalu besar'], ['Idempotency-Key' => 'refund-0003'])
        ->assertStatus(422)->assertJsonPath('code', 'refund.exceeds_paid');
    test()->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/refunds", ['payment_method_id' => $s['tunai']->id, 'amount' => 1000, 'reason' => 'x'], ['Idempotency-Key' => 'refund-0004'])->assertForbidden();
});

it('KSR-07 void oleh Admin atau Pemilik dengan alasan mengembalikan uang lewat refund dan menyimpan semua catatan', function () {
    $s = tagihanTerbit();
    bayarTagihan($s, 200000, $s['tunai']);
    bayarTagihan($s, 100000, $s['qris']);

    test()->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/void", ['reason' => 'Salah'])->assertForbidden();
    test()->actingAs($s['admin'])->post("/invoices/{$s['invoice']}/void", ['reason' => ''])->assertSessionHasErrors('reason');
    test()->actingAs($s['pemilik'])->post("/invoices/{$s['invoice']}/void", ['reason' => 'Pasien batal berobat'])->assertSessionHasNoErrors();

    $invoice = tagihanModel($s);
    expect($invoice->status)->toBe('void')->and($invoice->lifecycle)->toBe('void')->and($invoice->void_reason)->toBe('Pasien batal berobat')->and($invoice->voided_by)->toBe($s['pemilik']->id)
        ->and($invoice->paid_amount)->toBe(0)->and(PaymentModel::query()->count())->toBe(4)->and((int) PaymentModel::query()->where('type', 'refund')->sum('amount'))->toBe(300000)
        ->and(InvoiceItemModel::query()->count())->toBe(1)->and($invoice->deleted_at)->toBeNull()
        ->and(AuditLogModel::query()->where('action', 'invoice.voided')->exists())->toBeTrue();

    test()->actingAs($s['admin'])->postJson("/invoices/{$s['invoice']}/void", ['reason' => 'Lagi'])->assertStatus(409)->assertJsonPath('code', 'invoice.already_void');
    bayarTagihan($s, 1000, null, 'pay', 'key-setelah-void')->assertSessionHasErrors('domain');
});

it('KSR-07 pembayaran tidak dapat diubah atau dihapus lewat model (hanya penautan tutup kas sekali)', function () {
    $s = tagihanTerbit();
    bayarTagihan($s, 1000);

    tenant()->set($s['klinik']->id);
    $p = PaymentModel::query()->firstOrFail();
    expect(fn () => $p->forceFill(['amount' => 5])->save())->toThrow(LogicException::class)
        ->and(fn () => $p->delete())->toThrow(LogicException::class);
});

// --- Tampilan dan otorisasi baca ---

it('KSR-01 daftar dan detail tagihan memuat angka rupiah bulat, pasien, dan hak aksi sesuai peran', function () {
    $s = tagihanTerbit();
    bayarTagihan($s, 100000);

    test()->actingAs($s['kasir'])->get('/invoices')->assertOk()->assertInertia(fn ($p) => $p->component('Invoices/Index')
        ->where('total', 1)->where('rows.0.number', 'INV-2025-00001')->where('rows.0.total_amount', 500000)->where('rows.0.paid_amount', 100000)
        ->where('rows.0.balance_amount', 400000)->where('rows.0.status', 'partial')->where('rows.0.patient_name', 'Budi Santoso'));

    test()->actingAs($s['kasir'])->get("/invoices/{$s['invoice']}")->assertOk()->assertInertia(fn ($p) => $p->component('Invoices/Show')
        ->where('invoice.balance_amount', 400000)->where('items.0.unit_price_amount', 250000)->where('payments.0.method_name', 'Tunai')
        ->where('can.pay', true)->where('can.void', false));
    test()->actingAs($s['admin'])->get("/invoices/{$s['invoice']}")->assertInertia(fn ($p) => $p->where('can.pay', false)->where('can.void', true)->where('can.refund', true));
    test()->actingAs($s['dokter_user'])->get("/invoices/{$s['invoice']}")->assertOk();
    test()->actingAs($s['perawat'])->get("/invoices/{$s['invoice']}")->assertForbidden();
});

it('KSR-01 membuka tagihan tercatat di access log dan filter status bekerja', function () {
    $s = tagihanTerbit();

    test()->actingAs($s['kasir'])->get("/invoices/{$s['invoice']}")->assertOk();
    expect(DB::table('access_logs')->where('patient_id', $s['pasien'])->exists())->toBeTrue();

    test()->actingAs($s['kasir'])->get('/invoices?status=paid')->assertInertia(fn ($p) => $p->where('total', 0));
    test()->actingAs($s['kasir'])->get('/invoices?status=unpaid')->assertInertia(fn ($p) => $p->where('total', 1));
    test()->actingAs($s['kasir'])->get('/invoices?number=INV-2025')->assertInertia(fn ($p) => $p->where('total', 1));
    test()->actingAs($s['kasir'])->get('/invoices?status=bogus')->assertSessionHasErrors('status');
});

it('M3 tagihan klinik lain tidak terlihat dan tidak dapat dibayar', function () {
    $a = tagihanTerbit();
    $b = siapTagihan();

    test()->actingAs($b['kasir'])->get("/invoices/{$a['invoice']}")->assertNotFound();
    test()->actingAs($b['kasir'])->post("/invoices/{$a['invoice']}/payments", ['payment_method_id' => $b['tunai']->id, 'type' => 'pay', 'amount' => 1000], ['Idempotency-Key' => 'lintas-klinik-1'])->assertNotFound();
});

it('KSR-01 layar pemeriksaan mendapat ringkasan tagihan lewat kontrak Billing', function () {
    $s = tagihanTerbit();

    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertInertia(fn ($p) => $p->where('invoice.id', $s['invoice'])->where('invoice.number', 'INV-2025-00001')->where('invoice.status', 'unpaid'));
});

it('KSR-01 ringkasan tagihan untuk antarmuka dalam: total, dibayar, sisa', function () {
    $s = tagihanTerbit();
    bayarTagihan($s, 100000);

    test()->actingAs($s['kasir'])->getJson("/internal-api/invoices/{$s['invoice']}/summary")->assertOk()
        ->assertJsonPath('data.total_amount', 500000)->assertJsonPath('data.paid_amount', 100000)->assertJsonPath('data.balance_amount', 400000);
});

// --- KSR-06: cetak ---

it('KSR-06 invoice dan kwitansi dicetak sebagai PDF A4 atau termal 80 mm dan tercatat di audit', function () {
    $s = tagihanTerbit();
    bayarTagihan($s, 200000);
    $bayar = PaymentModel::query()->firstOrFail();

    foreach (['doc=invoice&format=a4', 'doc=invoice&format=thermal', "doc=receipt&format=a4&payment={$bayar->id}", 'doc=receipt&format=thermal'] as $query) {
        $r = test()->actingAs($s['kasir'])->get("/invoices/{$s['invoice']}/print?{$query}")->assertOk();
        expect($r->headers->get('Content-Type'))->toBe('application/pdf')->and(str_starts_with((string) $r->getContent(), '%PDF'))->toBeTrue()
            ->and($r->headers->get('Cache-Control'))->toContain('no-store');
    }
    $unduh = test()->actingAs($s['kasir'])->get("/invoices/{$s['invoice']}/print?download=1")->assertOk();
    tenant()->set($s['klinik']->id);
    expect($unduh->headers->get('Content-Disposition'))->toContain('attachment')->toContain('invoice-INV-2025-00001.pdf')
        ->and(AuditLogModel::query()->where('action', 'invoice.printed')->exists())->toBeTrue()
        ->and(AuditLogModel::query()->where('action', 'receipt.printed')->exists())->toBeTrue();
});

it('KSR-06 draft tanpa nomor tidak dicetak sebagai invoice; kwitansi tanpa pembayaran ditolak', function () {
    $s = siapTagihan();

    test()->actingAs($s['kasir'])->getJson("/invoices/{$s['invoice']}/print")->assertStatus(409)->assertJsonPath('code', 'invoice.not_issued');
    test()->actingAs($s['kasir'])->getJson("/invoices/{$s['invoice']}/print?doc=receipt")->assertStatus(422)->assertJsonPath('code', 'payment.not_found');
    test()->actingAs($s['perawat'])->get("/invoices/{$s['invoice']}/print")->assertForbidden();
});

// --- KSR-08: tutup kas ---

it('KSR-08 tutup kas menghitung tunai bersih kasir sendiri, mengabaikan non-tunai dan refund yang dicatat orang lain', function () {
    $s = tagihanTerbit();
    bayarTagihan($s, 200000, $s['tunai']);
    bayarTagihan($s, 100000, $s['qris']);
    $refundKasir = makeUser(Role::Kasir, $s['klinik']);
    $refundKasir->givePermissionTo('payment.refund');
    test()->actingAs($refundKasir)->post("/invoices/{$s['invoice']}/refunds", ['payment_method_id' => $s['tunai']->id, 'amount' => 50000, 'reason' => 'Koreksi'], ['Idempotency-Key' => 'refund-kas-001'])->assertSessionHasNoErrors();
    test()->actingAs($s['admin'])->post("/invoices/{$s['invoice']}/refunds", ['payment_method_id' => $s['tunai']->id, 'amount' => 10000, 'reason' => 'Koreksi admin'], ['Idempotency-Key' => 'refund-kas-002'])->assertSessionHasNoErrors();

    test()->actingAs($s['kasir'])->get('/cash-closings/create')->assertOk()->assertInertia(fn ($p) => $p->component('CashClosings/Form')->where('preview.system_amount', 200000)->where('preview.payments', 1));
    test()->actingAs($refundKasir)->get('/cash-closings/create')->assertInertia(fn ($p) => $p->where('preview.system_amount', -50000)->where('preview.payments', 1));

    test()->actingAs($s['kasir'])->post('/cash-closings', ['counted_amount' => 200000])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $tutup = CashClosingModel::query()->firstOrFail();
    expect($tutup->system_amount)->toBe(200000)->and($tutup->counted_amount)->toBe(200000)->and($tutup->difference_amount)->toBe(0)
        ->and($tutup->cashier_id)->toBe($s['kasir']->id)->and($tutup->closing_date->toDateString())->toBe('2025-06-16')
        ->and(PaymentModel::query()->whereNotNull('cash_closing_id')->count())->toBe(1) // hanya tunai milik kasir ini; QRIS dan refund orang lain tidak ikut
        ->and(AuditLogModel::query()->where('action', 'cash.closed')->exists())->toBeTrue();
});

it('KSR-08 selisih kas wajib dicatat dan penutupan berikutnya tidak menghitung ulang pembayaran yang sudah ditutup', function () {
    $s = tagihanTerbit();
    bayarTagihan($s, 200000, $s['tunai']);

    test()->actingAs($s['kasir'])->postJson('/cash-closings', ['counted_amount' => 190000])->assertStatus(422)->assertJsonPath('code', 'cash_closing.note_required');
    test()->actingAs($s['kasir'])->post('/cash-closings', ['counted_amount' => 190000, 'note' => 'Uang kembalian kurang'])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    expect(CashClosingModel::query()->firstOrFail()->difference_amount)->toBe(-10000);

    bayarTagihan($s, 50000, $s['tunai']);
    test()->actingAs($s['kasir'])->get('/cash-closings/create')->assertInertia(fn ($p) => $p->where('preview.system_amount', 50000)->where('preview.payments', 1));
    test()->actingAs($s['kasir'])->post('/cash-closings', ['counted_amount' => 50000])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    expect(CashClosingModel::query()->count())->toBe(2);

    expect(fn () => CashClosingModel::query()->firstOrFail()->forceFill(['counted_amount' => 1])->save())->toThrow(LogicException::class);
});

it('KSR-08 kas milik kasir lain tidak ikut dan hanya yang berizin yang dapat menutup kas', function () {
    $s = tagihanTerbit();
    $kasirLain = makeUser(Role::Kasir, $s['klinik']);
    bayarTagihan($s, 100000, $s['tunai']);

    test()->actingAs($kasirLain)->get('/cash-closings/create')->assertInertia(fn ($p) => $p->where('preview.system_amount', 0));
    test()->actingAs($kasirLain)->post('/cash-closings', ['counted_amount' => 0])->assertSessionHasNoErrors();
    test()->actingAs($s['kasir'])->get('/cash-closings/create')->assertInertia(fn ($p) => $p->where('preview.system_amount', 100000));

    test()->actingAs($s['admin'])->get('/cash-closings')->assertForbidden();
    test()->actingAs($s['dokter_user'])->post('/cash-closings', ['counted_amount' => 0])->assertForbidden();
    test()->actingAs($s['kasir'])->postJson('/cash-closings', ['counted_amount' => -5])->assertStatus(422);
});
