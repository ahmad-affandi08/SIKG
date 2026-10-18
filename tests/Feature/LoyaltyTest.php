<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Billing\Infrastructure\Eloquent\InvoiceModel;
use Src\Billing\Infrastructure\Eloquent\VisitPackageModel;
use Src\Billing\Infrastructure\Eloquent\VoucherModel;
use Src\Clinic\Infrastructure\Eloquent\PaymentMethodModel;
use Src\IdentityAccess\Domain\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Kunjungan berjalan dengan tagihan draft Rp 500.000 (2 x Rp 250.000), peran kasir dan admin, serta metode tunai.
 *
 * @return array<string, mixed>
 */
function siapPromo(bool $terbit = true): array
{
    $s = siapKunjungan();
    $s['kasir'] = makeUser(Role::Kasir, $s['klinik']);
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    tenant()->set($s['klinik']->id);
    $metode = new PaymentMethodModel;
    $metode->forceFill(['name' => 'Tunai', 'type' => 'cash', 'is_active' => true])->save();
    $s['tunai'] = $metode;
    $s['tindakan'] = tindakan($s, ['default_price_amount' => 250000]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $s['tindakan']->id, 'tooth_fdi' => 46, 'quantity' => 2])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $s['invoice'] = InvoiceModel::query()->where('encounter_id', $s['kunjungan'])->firstOrFail()->id;
    if ($terbit) {
        test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();
        tenant()->set($s['klinik']->id);
    }

    return $s;
}

/** @param array<string, mixed> $o */
function buatVoucher(array $s, array $o = []): VoucherModel
{
    tenant()->set($s['klinik']->id);
    $v = new VoucherModel;
    $v->forceFill(['code' => 'HEMAT10', 'name' => 'Hemat 10 persen', 'kind' => 'percent', 'value' => 10, 'min_subtotal_amount' => 0, 'max_per_patient' => 1, 'is_active' => true, ...$o])->save();

    return $v;
}

function bayarPromo(array $s, int $jumlah, ?string $invoice = null): void
{
    test()->actingAs($s['kasir'])->post('/invoices/'.($invoice ?? $s['invoice']).'/payments', ['payment_method_id' => $s['tunai']->id, 'type' => 'pay', 'amount' => $jumlah], ['Idempotency-Key' => 'k-'.uniqid()])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
}

function aturPoin(array $s, array $o = []): void
{
    test()->actingAs($s['admin'])->post('/settings/loyalty', ['enabled' => true, 'rupiah_per_point' => 10000, 'point_value_amount' => 1000, 'min_redeem_points' => 10, ...$o])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
}

function tagihanPromo(array $s, ?string $invoice = null): InvoiceModel
{
    tenant()->set($s['klinik']->id);

    return InvoiceModel::query()->findOrFail($invoice ?? $s['invoice']);
}

function saldoPoin(array $s): int
{
    return (int) DB::table('loyalty_balances')->where('patient_id', $s['pasien'])->value('points');
}

it('P3-06 kasir memakai voucher persen pada tagihan dan diskon tercatat dengan total yang diturunkan', function () {
    $s = siapPromo();
    $v = buatVoucher($s);

    $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/promotions/voucher", ['code' => ' hemat10 '])->assertSessionHasNoErrors();

    $inv = tagihanPromo($s);
    $promo = DB::table('invoice_promotions')->first();
    expect($inv->discount_amount)->toBe(50000)->and($inv->total_amount)->toBe(450000)->and($inv->discount_reason)->toBe('Voucher HEMAT10')
        ->and($promo->voucher_id)->toBe($v->id)->and($promo->status)->toBe('applied')->and($promo->discount_amount)->toBe(50000)->and((int) $promo->active_slot)->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'promo.voucher_applied')->count())->toBe(1);

    $this->get("/invoices/{$s['invoice']}/promotions")->assertInertia(fn ($p) => $p->component('Invoices/Promotions')->where('promotion.label', 'Voucher HEMAT10')->where('promotion.discount_amount', 50000));
});

it('P3-06 voucher nominal tidak melebihi subtotal dan satu tagihan hanya satu promo', function () {
    $s = siapPromo();
    buatVoucher($s, ['code' => 'BESAR', 'kind' => 'amount', 'value' => 900000]);
    buatVoucher($s, ['code' => 'LAIN']);

    $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/promotions/voucher", ['code' => 'BESAR'])->assertSessionHasNoErrors();
    expect(tagihanPromo($s)->total_amount)->toBe(0);

    $this->post("/invoices/{$s['invoice']}/promotions/voucher", ['code' => 'LAIN'])->assertSessionHas('error_code', 'promo.already_applied');
    expect(DB::table('invoice_promotions')->count())->toBe(1);
});

it('P3-06 voucher ditolak bila tidak ada, nonaktif, di luar masa berlaku, di bawah minimum, atau melewati batas', function () {
    $s = siapPromo();
    buatVoucher($s, ['code' => 'MATI', 'is_active' => false]);
    buatVoucher($s, ['code' => 'LAMA', 'valid_to' => '2025-06-01']);
    buatVoucher($s, ['code' => 'BELUM', 'valid_from' => '2025-07-01']);
    buatVoucher($s, ['code' => 'MINIMUM', 'min_subtotal_amount' => 600000]);
    $habis = buatVoucher($s, ['code' => 'HABIS', 'max_redemptions' => 1, 'max_per_patient' => 5]);
    $kode = fn (string $c) => $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/promotions/voucher", ['code' => $c]);

    $kode('TIDAKADA')->assertSessionHas('error_code', 'voucher.not_found');
    $kode('MATI')->assertSessionHas('error_code', 'voucher.inactive');
    $kode('LAMA')->assertSessionHas('error_code', 'voucher.expired');
    $kode('BELUM')->assertSessionHas('error_code', 'voucher.expired');
    $kode('MINIMUM')->assertSessionHas('error_code', 'voucher.min_subtotal');
    $kode('')->assertSessionHasErrors('code');

    // Pemakaian aktif oleh pasien lain menghabiskan batas total; setelah dilepas, voucher tersedia lagi.
    $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/promotions/voucher", ['code' => 'HABIS'])->assertSessionHasNoErrors();
    $lain = siapPromoUntukPasien($s, makePatient($s['klinik'], 'Pasien Lain'));
    $this->post("/invoices/{$lain}/promotions/voucher", ['code' => 'HABIS'])->assertSessionHas('error_code', 'voucher.exhausted');
    $this->post("/invoices/{$s['invoice']}/promotions/remove")->assertSessionHasNoErrors();
    $this->post("/invoices/{$lain}/promotions/voucher", ['code' => 'HABIS'])->assertSessionHasNoErrors();
    expect($habis->id)->toBe((string) DB::table('invoice_promotions')->where('status', 'applied')->value('voucher_id'));
});

it('P3-06 batas pemakaian per pasien dan total dihitung dari pemakaian aktif', function () {
    $s = siapPromo();
    buatVoucher($s, ['code' => 'SEKALI', 'max_per_patient' => 1]);
    $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/promotions/voucher", ['code' => 'SEKALI'])->assertSessionHasNoErrors();

    // Tagihan kedua pasien yang sama (kunjungan lain) tidak boleh memakai lagi.
    $kedua = siapPromoUntukPasien($s);
    $this->post("/invoices/{$kedua}/promotions/voucher", ['code' => 'SEKALI'])->assertSessionHas('error_code', 'voucher.patient_limit');

    // Setelah dilepas, pemakaian kembali tersedia.
    $this->post("/invoices/{$s['invoice']}/promotions/remove")->assertSessionHasNoErrors();
    $this->post("/invoices/{$kedua}/promotions/voucher", ['code' => 'SEKALI'])->assertSessionHasNoErrors();
    expect(DB::table('invoice_promotions')->where('status', 'released')->count())->toBe(1)->and(DB::table('invoice_promotions')->where('status', 'applied')->count())->toBe(1);
});

/** Tagihan kedua untuk pasien yang sama pada kunjungan baru. */
function siapPromoUntukPasien(array $s, ?string $pasien = null): string
{
    $pasien ??= $s['pasien'];
    tenant()->set($s['klinik']->id);
    $kunjungan = strtoupper((string) Str::ulid());
    $now = now();
    DB::table('encounters')->insert(['id' => $kunjungan, 'clinic_id' => $s['klinik']->id, 'patient_id' => $pasien, 'doctor_id' => $s['dokter']->id, 'started_at' => $now, 'status' => 'open', 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $invoice = strtoupper((string) Str::ulid());
    DB::table('invoices')->insert([
        'id' => $invoice, 'clinic_id' => $s['klinik']->id, 'number' => 'INV-X-'.substr($invoice, -5), 'patient_id' => $pasien, 'encounter_id' => $kunjungan, 'lifecycle' => 'issued', 'status' => 'unpaid',
        'subtotal_amount' => 300000, 'discount_amount' => 0, 'total_amount' => 300000, 'paid_amount' => 0, 'tax_amount' => 0, 'tax_basis_points' => 0, 'issued_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('invoice_items')->insert(['id' => strtoupper((string) Str::ulid()), 'clinic_id' => $s['klinik']->id, 'invoice_id' => $invoice, 'source_type' => 'manual', 'source_id' => null, 'description' => 'Item uji', 'quantity' => 1, 'unit_price_amount' => 300000, 'discount_amount' => 0, 'total_amount' => 300000, 'created_at' => $now, 'updated_at' => $now]);

    return $invoice;
}

it('P3-06 promo dan diskon manual tidak saling menimpa', function () {
    $s = siapPromo();
    buatVoucher($s);

    $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/discount", ['kind' => 'amount', 'value' => '5000', 'reason' => 'Promo kasir'])->assertSessionHasNoErrors();
    $this->post("/invoices/{$s['invoice']}/promotions/voucher", ['code' => 'HEMAT10'])->assertSessionHas('error_code', 'promo.discount_exists');
    $this->post("/invoices/{$s['invoice']}/discount", ['kind' => 'none'])->assertSessionHasNoErrors();

    $this->post("/invoices/{$s['invoice']}/promotions/voucher", ['code' => 'HEMAT10'])->assertSessionHasNoErrors();
    $this->post("/invoices/{$s['invoice']}/discount", ['kind' => 'amount', 'value' => '5000', 'reason' => 'Menimpa'])->assertSessionHas('error_code', 'promo.active');
    $this->post("/invoices/{$s['invoice']}/discount", ['kind' => 'none'])->assertSessionHas('error_code', 'promo.active');
    expect(tagihanPromo($s)->discount_amount)->toBe(50000);
});

it('P3-06 promo tidak dapat diubah setelah ada pembayaran', function () {
    $s = siapPromo();
    buatVoucher($s);
    bayarPromo($s, 100000);

    $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/promotions/voucher", ['code' => 'HEMAT10'])->assertSessionHas('error_code', 'invoice.not_editable');
    $this->get("/invoices/{$s['invoice']}/promotions")->assertInertia(fn ($p) => $p->where('invoice.editable', false));
});

it('P3-06 poin didapat sekali saat tagihan lunas menurut pengaturan klinik', function () {
    $s = siapPromo();
    aturPoin($s);

    bayarPromo($s, 200000);
    expect(saldoPoin($s))->toBe(0);
    bayarPromo($s, 300000);

    expect(saldoPoin($s))->toBe(50)->and(DB::table('loyalty_ledger')->where('kind', 'earn')->count())->toBe(1);
    $baris = DB::table('loyalty_ledger')->first();
    expect((int) $baris->delta)->toBe(50)->and((int) $baris->balance_after)->toBe(50)->and($baris->invoice_id)->toBe($s['invoice']);
});

it('P3-06 poin tidak didapat bila pengaturan mati', function () {
    $s = siapPromo();

    bayarPromo($s, 500000);

    expect(DB::table('loyalty_ledger')->count())->toBe(0);
});

it('P3-06 poin ditukar menjadi diskon, dapat dilepas, dan batasnya dijaga', function () {
    $s = siapPromo();
    aturPoin($s);
    bayarPromo($s, 500000);
    $kedua = siapPromoUntukPasien($s);
    $tukar = fn (int $poin) => $this->actingAs($s['kasir'])->post("/invoices/{$kedua}/promotions/points", ['points' => $poin]);

    $tukar(5)->assertSessionHas('error_code', 'points.below_minimum');
    $tukar(60)->assertSessionHas('error_code', 'points.insufficient');
    $tukar(0)->assertSessionHasErrors('points');
    DB::table('loyalty_balances')->where('patient_id', $s['pasien'])->update(['points' => 400]);
    $tukar(301)->assertSessionHas('error_code', 'points.exceeds_subtotal');

    $tukar(20)->assertSessionHasNoErrors();
    expect(saldoPoin($s))->toBe(380)->and((int) DB::table('invoices')->where('id', $kedua)->value('discount_amount'))->toBe(20000)->and((int) DB::table('invoices')->where('id', $kedua)->value('total_amount'))->toBe(280000);

    $this->post("/invoices/{$kedua}/promotions/remove")->assertSessionHasNoErrors();
    expect(saldoPoin($s))->toBe(400)->and((int) DB::table('invoices')->where('id', $kedua)->value('discount_amount'))->toBe(0)
        ->and(DB::table('loyalty_ledger')->whereIn('kind', ['redeem', 'release'])->count())->toBe(2);
});

it('P3-06 membatalkan tagihan membalik poin yang didapat dan mengembalikan poin yang ditukar', function () {
    $s = siapPromo();
    aturPoin($s);
    bayarPromo($s, 500000);
    expect(saldoPoin($s))->toBe(50);
    $kedua = siapPromoUntukPasien($s);
    $this->actingAs($s['kasir'])->post("/invoices/{$kedua}/promotions/points", ['points' => 20])->assertSessionHasNoErrors();
    expect(saldoPoin($s))->toBe(30);

    $this->actingAs($s['admin'])->post("/invoices/{$kedua}/void", ['reason' => 'Salah input'])->assertSessionHasNoErrors();
    expect(saldoPoin($s))->toBe(50)->and(DB::table('invoice_promotions')->where('invoice_id', $kedua)->value('status'))->toBe('released');

    $this->post("/invoices/{$s['invoice']}/void", ['reason' => 'Dibatalkan'])->assertSessionHasNoErrors();
    expect(saldoPoin($s))->toBe(0)->and(DB::table('loyalty_ledger')->where('kind', 'reverse')->count())->toBe(1);
});

it('P3-06 paket kunjungan dijual lewat tagihan kunjungan berjalan dan sesinya dipakai pada item', function () {
    $s = siapPromo(false);
    $paket = new VisitPackageModel;
    $paket->forceFill(['name' => 'Paket Scaling 3x', 'sessions' => 3, 'price_amount' => 600000, 'max_session_value_amount' => 150000, 'valid_days' => 30, 'is_active' => true])->save();

    $this->actingAs($s['kasir'])->post("/patients/{$s['pasien']}/packages", ['package_id' => $paket->id, 'encounter_id' => $s['kunjungan']])->assertSessionHasNoErrors();

    $pp = DB::table('patient_packages')->first();
    expect($pp->name)->toBe('Paket Scaling 3x')->and((int) $pp->sessions_left)->toBe(3)->and((int) $pp->price_amount)->toBe(600000)->and($pp->status)->toBe('active')->and($pp->expires_on)->toStartWith('2025-07-16')
        ->and(DB::table('invoice_items')->where('source_type', 'package')->where('source_id', $pp->id)->value('unit_price_amount'))->toBe(600000);

    // Sesi menanggung paling banyak Rp 150.000 dari item bertarif Rp 250.000.
    $item = (string) DB::table('invoice_items')->where('source_type', 'procedure')->value('id');
    $this->post("/invoices/{$s['invoice']}/promotions/package", ['item_id' => $item, 'patient_package_id' => $pp->id])->assertSessionHasNoErrors();
    $itemBaris = DB::table('invoice_items')->where('id', $item)->first();
    expect((int) $itemBaris->discount_amount)->toBe(150000)->and((int) DB::table('patient_packages')->where('id', $pp->id)->value('sessions_left'))->toBe(2)
        ->and(DB::table('package_usages')->where('status', 'applied')->count())->toBe(1);

    $this->post("/invoices/{$s['invoice']}/promotions/package", ['item_id' => $item, 'patient_package_id' => $pp->id])->assertSessionHas('error_code', 'package.item_discounted');
    $this->post("/invoices/{$s['invoice']}/items/{$item}/discount", ['kind' => 'none'])->assertSessionHas('error_code', 'promo.active');

    $this->post("/invoices/{$s['invoice']}/promotions/package/remove", ['item_id' => $item])->assertSessionHasNoErrors();
    expect((int) DB::table('invoice_items')->where('id', $item)->value('discount_amount'))->toBe(0)->and((int) DB::table('patient_packages')->where('id', $pp->id)->value('sessions_left'))->toBe(3);
});

it('P3-06 paket menolak sesi habis, kedaluwarsa, milik pasien lain, dan penjualan ke kunjungan yang salah', function () {
    $s = siapPromo(false);
    $paket = new VisitPackageModel;
    $paket->forceFill(['name' => 'Paket Uji', 'sessions' => 1, 'price_amount' => 100000, 'valid_days' => 10, 'is_active' => true])->save();
    $this->actingAs($s['kasir'])->post("/patients/{$s['pasien']}/packages", ['package_id' => $paket->id, 'encounter_id' => $s['kunjungan']])->assertSessionHasNoErrors();
    $pp = DB::table('patient_packages')->first();
    $item = (string) DB::table('invoice_items')->where('source_type', 'procedure')->value('id');
    $pakai = fn () => $this->post("/invoices/{$s['invoice']}/promotions/package", ['item_id' => $item, 'patient_package_id' => $pp->id]);

    $pakai()->assertSessionHasNoErrors();
    $this->post("/invoices/{$s['invoice']}/promotions/package/remove", ['item_id' => $item])->assertSessionHasNoErrors();
    DB::table('patient_packages')->where('id', $pp->id)->update(['sessions_left' => 0]);
    $pakai()->assertSessionHas('error_code', 'package.exhausted');

    DB::table('patient_packages')->where('id', $pp->id)->update(['sessions_left' => 1, 'expires_on' => '2025-06-01']);
    $pakai()->assertSessionHas('error_code', 'package.expired');

    DB::table('patient_packages')->where('id', $pp->id)->update(['expires_on' => null, 'patient_id' => makePatient($s['klinik'], 'Pasien Lain')]);
    $pakai()->assertSessionHas('error_code', 'package.not_found');

    $kunjunganLain = (string) DB::table('invoices')->where('id', siapPromoUntukPasien($s, makePatient($s['klinik'], 'Pasien Kunjungan Lain')))->value('encounter_id');
    $this->post("/patients/{$s['pasien']}/packages", ['package_id' => $paket->id, 'encounter_id' => $kunjunganLain])->assertSessionHas('error_code', 'package.encounter_invalid');
    DB::table('visit_packages')->update(['is_active' => false]);
    $this->post("/patients/{$s['pasien']}/packages", ['package_id' => $paket->id, 'encounter_id' => $s['kunjungan']])->assertSessionHas('error_code', 'package.catalog_not_found');
});

it('P3-06 membatalkan tagihan pembelian membatalkan paket dan membatalkan tagihan pemakaian mengembalikan sesinya', function () {
    $s = siapPromo(false);
    $paket = new VisitPackageModel;
    $paket->forceFill(['name' => 'Paket Uji', 'sessions' => 2, 'price_amount' => 100000, 'is_active' => true])->save();
    $this->actingAs($s['kasir'])->post("/patients/{$s['pasien']}/packages", ['package_id' => $paket->id, 'encounter_id' => $s['kunjungan']])->assertSessionHasNoErrors();
    $pp = DB::table('patient_packages')->first();
    $item = (string) DB::table('invoice_items')->where('source_type', 'procedure')->value('id');
    $this->post("/invoices/{$s['invoice']}/promotions/package", ['item_id' => $item, 'patient_package_id' => $pp->id])->assertSessionHasNoErrors();
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);

    $this->actingAs($s['admin'])->post("/invoices/{$s['invoice']}/void", ['reason' => 'Batal total'])->assertSessionHasNoErrors();

    expect(DB::table('patient_packages')->where('id', $pp->id)->value('status'))->toBe('void')->and(DB::table('package_usages')->value('status'))->toBe('released')
        ->and(DB::table('audit_logs')->where('action', 'promo.reversed_on_void')->count())->toBe(1);
});

it('P3-06 voucher dan paket dikelola sebagai master data, termasuk tanggal berlaku', function () {
    $s = siapPromo();

    $this->actingAs($s['admin'])->post('/settings/master/vouchers', [
        'code' => 'promo-baru', 'name' => 'Promo baru', 'kind' => 'percent', 'value' => 15, 'min_subtotal_amount' => 0, 'max_per_patient' => 2, 'valid_from' => '2025-06-01', 'valid_to' => '2025-06-30',
    ])->assertSessionHasNoErrors();
    expect(DB::table('vouchers')->where('code', 'PROMO-BARU')->exists())->toBeTrue();

    $this->post('/settings/master/vouchers', ['code' => 'PROMO-BARU', 'name' => 'Ganda', 'kind' => 'percent', 'value' => 5, 'min_subtotal_amount' => 0, 'max_per_patient' => 1])->assertSessionHasErrors('code');
    $this->post('/settings/master/vouchers', ['code' => 'LEBIH', 'name' => 'Lebih', 'kind' => 'percent', 'value' => 101, 'min_subtotal_amount' => 0, 'max_per_patient' => 1])->assertSessionHasErrors('value');
    $this->post('/settings/master/vouchers', ['code' => 'TERBALIK', 'name' => 'Terbalik', 'kind' => 'amount', 'value' => 5000, 'min_subtotal_amount' => 0, 'max_per_patient' => 1, 'valid_from' => '2025-06-30', 'valid_to' => '2025-06-01'])->assertSessionHasErrors('valid_to');
    $this->post('/settings/master/visit-packages', ['name' => 'Paket 5x', 'sessions' => 5, 'price_amount' => 900000])->assertSessionHasNoErrors();
    expect(DB::table('visit_packages')->where('name', 'Paket 5x')->exists())->toBeTrue();

    $this->actingAs($s['kasir'])->post('/settings/master/vouchers', ['code' => 'X', 'name' => 'x', 'kind' => 'amount', 'value' => 1, 'min_subtotal_amount' => 0, 'max_per_patient' => 1])->assertForbidden();
});

it('P3-06 hak akses promo: kasir memakai, dokter hanya melihat, pengaturan poin hanya Admin', function () {
    $s = siapPromo();
    buatVoucher($s);

    $this->actingAs($s['dokter_user'])->post("/invoices/{$s['invoice']}/promotions/voucher", ['code' => 'HEMAT10'])->assertForbidden();
    $this->actingAs($s['kasir'])->post('/settings/loyalty', ['enabled' => true, 'rupiah_per_point' => 1, 'point_value_amount' => 1, 'min_redeem_points' => 1])->assertForbidden();
    $this->actingAs($s['admin'])->post('/settings/loyalty', ['enabled' => true, 'rupiah_per_point' => 0, 'point_value_amount' => 1000, 'min_redeem_points' => 10])->assertSessionHasErrors('rupiah_per_point');

    $this->actingAs($s['kasir'])->get("/patients/{$s['pasien']}/loyalty")->assertOk()->assertInertia(fn ($p) => $p->component('Patients/Loyalty')->where('can.sell', true)->where('loyalty.enabled', false));
    expect(DB::table('invoice_promotions')->count())->toBe(0);
});

it('P3-06 promo pada tagihan klinik lain tidak ditemukan', function () {
    $s = siapPromo();
    buatVoucher($s);
    $lain = siapPromo();

    $this->actingAs($lain['kasir'])->post("/invoices/{$s['invoice']}/promotions/voucher", ['code' => 'HEMAT10'])->assertNotFound();
});

it('P3-06 penggabungan pasien memindahkan paket, promosi, dan poin', function () {
    $s = siapPromo();
    aturPoin($s);
    bayarPromo($s, 500000);
    $utama = makePatient($s['klinik'], 'Pasien Utama');
    tenant()->set($s['klinik']->id);

    $mover = app()->tagged('sikg.patient-movers');
    $label = [];
    foreach ($mover as $m) {
        $label[$m->label()] = $m;
    }
    expect($label)->toHaveKeys(['Poin loyalitas', 'Paket kunjungan', 'Promosi tagihan'])->and($label['Poin loyalitas']->count($s['pasien']))->toBe(1);

    $label['Poin loyalitas']->move($s['pasien'], $utama);

    expect(saldoPoin($s))->toBe(0)->and((int) DB::table('loyalty_balances')->where('patient_id', $utama)->value('points'))->toBe(50)->and(DB::table('loyalty_ledger')->where('patient_id', $utama)->count())->toBe(1);
});
