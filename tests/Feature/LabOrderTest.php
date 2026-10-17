<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\IdentityAccess\Domain\Role;
use Src\Lab\Infrastructure\Eloquent\LaboratoryModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Kunjungan berjalan pasien, laboratorium aktif, serta peran kasir dan admin.
 *
 * @return array<string, mixed>
 */
function siapLab(): array
{
    $s = siapKunjungan();
    $s['kasir'] = makeUser(Role::Kasir, $s['klinik']);
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    tenant()->set($s['klinik']->id);
    $lab = new LaboratoryModel;
    $lab->forceFill(['name' => 'Dental Lab Prima', 'is_active' => true])->save();
    $s['lab'] = $lab;

    return $s;
}

/** @param array<string, mixed> $o */
function pesanLab(array $s, array $o = [])
{
    return test()->actingAs($s['dokter_user'])->post('/lab-orders', [
        'patient_id' => $s['pasien'], 'doctor_id' => $s['dokter']->id, 'laboratory_id' => $s['lab']->id, 'work_type' => 'crown',
        'tooth_notes' => '46', 'description' => 'Mahkota zirkonia', 'shade' => 'A2', 'due_date' => '2025-06-30',
        'lab_cost_amount' => 450000, 'patient_charge_amount' => 1200000, ...$o,
    ]);
}

function idPesananLab(): string
{
    return (string) DB::table('lab_orders')->orderByDesc('created_at')->orderByDesc('id')->value('id');
}

function terimaLab(array $s, string $id): void
{
    test()->actingAs($s['dokter_user'])->post("/lab-orders/{$id}/send", ['date' => '2025-06-17'])->assertSessionHasNoErrors();
    test()->post("/lab-orders/{$id}/receive", ['date' => '2025-06-24', 'lab_cost_amount' => 500000])->assertSessionHasNoErrors();
}

it('P2-06 dokter memesan pekerjaan lab dengan nomor berurutan, biaya lab, dan harga ke pasien', function () {
    $s = siapLab();

    pesanLab($s)->assertSessionHasNoErrors()->assertRedirect('/lab-orders/'.idPesananLab());
    pesanLab($s, ['work_type' => 'denture', 'description' => 'Gigi tiruan sebagian'])->assertSessionHasNoErrors();

    $baris = DB::table('lab_orders')->orderBy('order_no')->get();
    expect($baris->pluck('order_no')->all())->toBe(['LAB-2025-00001', 'LAB-2025-00002'])
        ->and($baris[0]->status)->toBe('ordered')->and($baris[0]->lab_cost_amount)->toBe(450000)->and($baris[0]->patient_charge_amount)->toBe(1200000)
        ->and($baris[0]->ordered_date)->toStartWith('2025-06-16')
        ->and(DB::table('audit_logs')->where('action', 'lab.order_placed')->count())->toBe(2);
});

it('P2-06 masukan pesanan yang salah ditolak', function () {
    $s = siapLab();

    pesanLab($s, ['description' => ''])->assertSessionHasErrors('description');
    pesanLab($s, ['work_type' => 'lain'])->assertSessionHasErrors('work_type');
    pesanLab($s, ['lab_cost_amount' => '450.000'])->assertSessionHasErrors('lab_cost_amount');
    pesanLab($s, ['patient_charge_amount' => -5])->assertSessionHasErrors('patient_charge_amount');
    pesanLab($s, ['due_date' => '2025-06-01'])->assertSessionHas('error_code', 'lab.due_before_order');
    pesanLab($s, ['laboratory_id' => strtoupper((string) Str::ulid())])->assertSessionHas('error_code', 'lab.laboratory_invalid');
    DB::table('laboratories')->update(['is_active' => false]);
    pesanLab($s)->assertSessionHas('error_code', 'lab.laboratory_invalid');
    expect(DB::table('lab_orders')->count())->toBe(0);
});

it('P2-06 status berjalan dari dipesan ke dikirim lalu diterima dengan biaya lab aktual', function () {
    $s = siapLab();
    pesanLab($s);
    $id = idPesananLab();

    $this->post("/lab-orders/{$id}/receive", ['date' => '2025-06-20', 'lab_cost_amount' => 1])->assertSessionHas('error_code', 'lab.not_sent');
    $this->post("/lab-orders/{$id}/send", ['date' => '2025-06-17'])->assertSessionHasNoErrors();
    $this->post("/lab-orders/{$id}/send", ['date' => '2025-06-17'])->assertSessionHas('error_code', 'lab.not_ordered');
    $this->post("/lab-orders/{$id}/receive", ['date' => '2025-06-16', 'lab_cost_amount' => 1])->assertSessionHas('error_code', 'lab.date_before_sent');
    $this->post("/lab-orders/{$id}/receive", ['date' => '2025-06-24', 'lab_cost_amount' => 500000])->assertSessionHasNoErrors();

    $baris = DB::table('lab_orders')->where('id', $id)->first();
    expect($baris->status)->toBe('received')->and($baris->sent_date)->toStartWith('2025-06-17')->and($baris->received_date)->toStartWith('2025-06-24')
        ->and($baris->lab_cost_amount)->toBe(500000)->and($baris->patient_charge_amount)->toBe(1200000)
        ->and(DB::table('audit_logs')->whereIn('action', ['lab.order_sent', 'lab.order_received'])->count())->toBe(2);
});

it('P2-06 pembatalan butuh alasan dan tidak berlaku setelah diterima', function () {
    $s = siapLab();
    pesanLab($s);
    $id = idPesananLab();

    $this->post("/lab-orders/{$id}/cancel", [])->assertSessionHasErrors('reason');
    $this->post("/lab-orders/{$id}/cancel", ['reason' => 'Pasien batal'])->assertSessionHasNoErrors();
    expect(DB::table('lab_orders')->where('id', $id)->value('status'))->toBe('cancelled')->and(DB::table('lab_orders')->where('id', $id)->value('cancel_note'))->toBe('Pasien batal');

    pesanLab($s);
    $kedua = idPesananLab();
    terimaLab($s, $kedua);
    $this->post("/lab-orders/{$kedua}/cancel", ['reason' => 'x'])->assertSessionHas('error_code', 'lab.cannot_cancel');
});

it('P2-06 kasir menagihkan harga ke pasien sebagai item tagihan kunjungan sekali saja', function () {
    $s = siapLab();
    pesanLab($s);
    $id = idPesananLab();

    $this->actingAs($s['kasir'])->post("/lab-orders/{$id}/charge", ['encounter_id' => $s['kunjungan']])->assertSessionHas('error_code', 'lab.not_received');
    terimaLab($s, $id);

    $this->actingAs($s['kasir'])->post("/lab-orders/{$id}/charge", ['encounter_id' => $s['kunjungan']])->assertSessionHasNoErrors();
    $baris = DB::table('lab_orders')->where('id', $id)->first();
    $item = DB::table('invoice_items')->where('source_type', 'lab')->where('source_id', $id)->first();
    expect($baris->invoice_id)->not->toBeNull()->and($item)->not->toBeNull()->and($item->unit_price_amount)->toBe(1200000)->and($item->description)->toContain('Mahkota')->toContain('LAB-2025-00001')
        ->and(DB::table('invoices')->where('id', $baris->invoice_id)->value('total_amount'))->toBe(1200000);

    $this->post("/lab-orders/{$id}/charge", ['encounter_id' => $s['kunjungan']])->assertSessionHas('error_code', 'lab.already_billed');
    expect(DB::table('invoice_items')->where('source_type', 'lab')->count())->toBe(1);
});

it('P2-06 penagihan menolak kunjungan pasien lain, kunjungan tak dikenal, dan harga nol', function () {
    $s = siapLab();
    pesanLab($s);
    $id = idPesananLab();
    terimaLab($s, $id);
    $lain = siapKunjungan();

    $this->actingAs($s['kasir'])->post("/lab-orders/{$id}/charge", ['encounter_id' => $lain['kunjungan']])->assertSessionHas('error_code', 'lab.encounter_invalid');
    $this->post("/lab-orders/{$id}/charge", ['encounter_id' => strtoupper((string) Str::ulid())])->assertSessionHas('error_code', 'lab.encounter_invalid');
    $this->post("/lab-orders/{$id}/charge", [])->assertSessionHasErrors('encounter_id');

    tenant()->set($s['klinik']->id);
    pesanLab($s, ['patient_charge_amount' => 0]);
    $gratis = idPesananLab();
    terimaLab($s, $gratis);
    $this->actingAs($s['kasir'])->post("/lab-orders/{$gratis}/charge", ['encounter_id' => $s['kunjungan']])->assertSessionHas('error_code', 'lab.no_charge');
    expect(DB::table('invoice_items')->where('source_type', 'lab')->count())->toBe(0);
});

it('P2-06 hak akses: dokter memesan, kasir menagih dan melihat tanpa uraian klinis, peran lain ditolak', function () {
    $s = siapLab();
    pesanLab($s);
    $id = idPesananLab();
    $resepsionis = makeUser(Role::Resepsionis, $s['klinik']);

    $this->actingAs($s['dokter_user'])->get("/lab-orders/{$id}")->assertOk()->assertInertia(fn ($p) => $p->component('Lab/Show')->where('can_clinical', true)->where('order.description', 'Mahkota zirkonia')->where('can.charge', false)->where('can.update', true));
    $this->actingAs($s['kasir'])->get("/lab-orders/{$id}")->assertOk()->assertInertia(fn ($p) => $p->where('can_clinical', false)->where('order.description', null)->where('order.tooth_notes', null)->where('order.lab_cost_amount', 450000)->where('can.charge', true)->where('can.update', false));
    $this->get('/lab-orders')->assertOk()->assertInertia(fn ($p) => $p->component('Lab/Index')->has('orders', 1)->where('orders.0.order_no', 'LAB-2025-00001'));

    $this->post("/lab-orders/{$id}/send", ['date' => '2025-06-17'])->assertForbidden();
    $this->post('/lab-orders', [])->assertForbidden();
    $this->actingAs($s['dokter_user'])->post("/lab-orders/{$id}/charge", ['encounter_id' => $s['kunjungan']])->assertForbidden();
    $this->actingAs($resepsionis)->post("/lab-orders/{$id}/send", ['date' => '2025-06-17'])->assertForbidden();
});

it('P2-06 pembukaan pesanan tercatat di jejak akses dan laboratorium dikelola sebagai master data', function () {
    $s = siapLab();
    pesanLab($s);
    $id = idPesananLab();

    $this->actingAs($s['dokter_user'])->get("/lab-orders/{$id}")->assertOk();
    expect(DB::table('access_logs')->where('route', 'lab-orders.show')->count())->toBe(1);

    $this->actingAs($s['admin'])->post('/settings/master/laboratories', ['name' => 'Lab Cemerlang', 'phone' => '021-555-0199'])->assertSessionHasNoErrors();
    expect(DB::table('laboratories')->where('name', 'Lab Cemerlang')->exists())->toBeTrue();
    $this->actingAs($s['kasir'])->post('/settings/master/laboratories', ['name' => 'Y'])->assertForbidden();
});

it('P2-06 pesanan hanya terlihat oleh klinik pemiliknya', function () {
    $s = siapLab();
    pesanLab($s);
    $id = idPesananLab();
    $lain = siapLab();

    $this->actingAs($lain['dokter_user'])->get("/lab-orders/{$id}")->assertSessionHas('error_code', 'lab.not_found');
    $this->actingAs($lain['dokter_user'])->get('/lab-orders')->assertInertia(fn ($p) => $p->has('orders', 0));
});
