<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Domain\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Kunjungan berjalan pasien, ditambah peran kasir dan admin.
 *
 * @return array<string, mixed>
 */
function siapOrtho(): array
{
    $s = siapKunjungan();
    $s['kasir'] = makeUser(Role::Kasir, $s['klinik']);
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);

    return $s;
}

/** @param array<string, mixed> $o */
function buatPaketOrtho(array $s, array $o = [])
{
    return test()->actingAs($s['dokter_user'])->post('/orthodontics', [
        'patient_id' => $s['pasien'], 'doctor_id' => $s['dokter']->id, 'appliance' => 'metal_braces', 'title' => 'Behel logam', 'plan_notes' => 'Rapikan gigi depan',
        'total_fee_amount' => 6000000, 'start_date' => '2025-06-16', 'expected_months' => 18, 'installment_count' => 3, 'down_payment_amount' => 0, ...$o,
    ]);
}

function idPaketOrtho(): string
{
    return (string) DB::table('orthodontic_cases')->orderByDesc('created_at')->orderByDesc('id')->value('id');
}

it('P2-04 dokter membuat paket beserta jadwal cicilan yang jumlahnya sama dengan biaya', function () {
    $s = siapOrtho();

    buatPaketOrtho($s, ['total_fee_amount' => 6000001, 'installment_count' => 3])->assertSessionHasNoErrors()->assertRedirect('/orthodontics/cases/'.idPaketOrtho());

    $paket = DB::table('orthodontic_cases')->first();
    expect($paket->status)->toBe('active')->and($paket->total_fee_amount)->toBe(6000001)->and($paket->appliance)->toBe('metal_braces');
    $cicilan = DB::table('orthodontic_installments')->orderBy('seq')->get();
    expect($cicilan->pluck('amount')->all())->toBe([2000000, 2000000, 2000001])->and($cicilan->pluck('due_date')->all())->toBe(['2025-06-16', '2025-07-16', '2025-08-16'])
        ->and($cicilan->pluck('status')->unique()->all())->toBe(['scheduled']);
    expect(DB::table('audit_logs')->where('action', 'orthodontic.case_created')->count())->toBe(1);
});

it('P2-04 uang muka menjadi cicilan pertama dan masukan yang salah ditolak', function () {
    $s = siapOrtho();

    buatPaketOrtho($s, ['down_payment_amount' => 2000000, 'installment_count' => 3])->assertSessionHasNoErrors();
    expect(DB::table('orthodontic_installments')->orderBy('seq')->pluck('amount')->all())->toBe([2000000, 2000000, 2000000]);

    buatPaketOrtho($s, ['title' => ''])->assertSessionHasErrors('title');
    buatPaketOrtho($s, ['appliance' => 'lain'])->assertSessionHasErrors('appliance');
    buatPaketOrtho($s, ['down_payment_amount' => 7000000])->assertSessionHas('error_code', 'ortho.down_payment_invalid');
    buatPaketOrtho($s, ['start_date' => '2025-02-30'])->assertSessionHasErrors('start_date');
    expect(DB::table('orthodontic_cases')->count())->toBe(1);
});

it('P2-04 izin: dokter membuat paket, perawat dan kasir tidak', function () {
    $s = siapOrtho();

    $this->actingAs($s['kasir'])->post('/orthodontics', ['patient_id' => $s['pasien']])->assertForbidden();
    $this->get("/orthodontics/create?patient={$s['pasien']}")->assertForbidden();
    $this->actingAs($s['perawat'])->get("/orthodontics/create?patient={$s['pasien']}")->assertForbidden();
    $this->actingAs($s['dokter_user'])->get("/orthodontics/create?patient={$s['pasien']}")->assertOk()->assertInertia(fn ($p) => $p->component('Orthodontics/Create')->has('doctors', 1)->has('appliances', 5));
});

it('P2-04 kontrol bulanan hanya bertambah, memerlukan isi, dan tidak boleh di masa depan', function () {
    $s = siapOrtho();
    buatPaketOrtho($s);
    $id = idPaketOrtho();
    $kirim = fn (array $d) => $this->actingAs($s['perawat'])->post("/orthodontics/cases/{$id}/controls", $d);

    $kirim(['control_date' => '2025-06-16', 'wire_or_tray' => 'NiTi 0.014', 'adjustments' => 'Pasang kawat awal', 'next_control_date' => '2025-07-14'])->assertSessionHasNoErrors();
    $baris = DB::table('orthodontic_controls')->first();
    expect($baris->wire_or_tray)->toBe('NiTi 0.014')->and($baris->next_control_date)->toBe('2025-07-14')->and($baris->created_by)->toBe($s['perawat']->id);

    $kirim(['control_date' => '2025-06-16'])->assertSessionHas('error_code', 'ortho.control_empty');
    $kirim(['control_date' => '2025-06-17', 'notes' => 'x'])->assertSessionHas('error_code', 'ortho.date_invalid');
    $kirim(['control_date' => '2025-06-16', 'notes' => 'x', 'next_control_date' => '2025-06-01'])->assertSessionHas('error_code', 'ortho.date_invalid');
    $kirim(['control_date' => '2025-06-16', 'notes' => 'x', 'encounter_id' => strtoupper((string) Str::ulid())])->assertSessionHas('error_code', 'ortho.encounter_invalid');
    expect(DB::table('orthodontic_controls')->count())->toBe(1);

    $this->actingAs($s['kasir'])->post("/orthodontics/cases/{$id}/controls", ['control_date' => '2025-06-16', 'notes' => 'x'])->assertForbidden();
});

it('P2-04 kontrol menautkan kunjungan sehingga foto progres tampil, dan kasir tidak melihat catatan klinis', function () {
    $s = siapOrtho();
    buatPaketOrtho($s);
    $id = idPaketOrtho();
    $this->post("/orthodontics/cases/{$id}/controls", ['control_date' => '2025-06-16', 'adjustments' => 'Ganti kawat', 'encounter_id' => $s['kunjungan']])->assertSessionHasNoErrors();
    DB::table('clinical_files')->insert([
        'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $s['klinik']->id, 'patient_id' => $s['pasien'], 'encounter_id' => $s['kunjungan'], 'disk' => 'private', 'path' => 'x/y.jpg',
        'mime' => 'image/jpeg', 'size' => 10, 'kind' => 'photo', 'caption' => 'Foto frontal', 'uploaded_by' => $s['dokter_user']->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get("/orthodontics/cases/{$id}")->assertOk()->assertInertia(fn ($p) => $p->component('Orthodontics/Show')->where('can_clinical', true)->where('case.plan_notes', 'Rapikan gigi depan')
        ->has('controls', 1)->where('controls.0.photos.0.caption', 'Foto frontal')->where('can.control', true)->where('can.manage', true)->where('can.charge', false));
    expect(DB::table('access_logs')->where('patient_id', $s['pasien'])->where('route', 'orthodontics.show')->exists())->toBeTrue();

    $this->actingAs($s['kasir'])->get("/orthodontics/cases/{$id}")->assertOk()->assertInertia(fn ($p) => $p->where('can_clinical', false)->where('case.plan_notes', null)->has('controls', 0)->has('installments', 3)->where('can.charge', true));
});

it('P2-04 cicilan ditagihkan sebagai item tagihan kunjungan berjalan dan tidak dua kali', function () {
    $s = siapOrtho();
    buatPaketOrtho($s);
    $cicilan = (string) DB::table('orthodontic_installments')->orderBy('seq')->value('id');

    $this->actingAs($s['kasir'])->post("/orthodontics/payment-plan/{$cicilan}/charge", ['encounter_id' => $s['kunjungan']])->assertSessionHasNoErrors();

    $baris = DB::table('orthodontic_installments')->where('id', $cicilan)->first();
    expect($baris->status)->toBe('invoiced')->and($baris->encounter_id)->toBe($s['kunjungan'])->and($baris->invoice_id)->not->toBeNull();
    $item = DB::table('invoice_items')->where('source_type', 'ortho')->where('source_id', $cicilan)->first();
    expect($item)->not->toBeNull()->and($item->unit_price_amount)->toBe(2000000)->and($item->description)->toContain('Cicilan ortodonti 1/3')->toContain('Behel logam')
        ->and(DB::table('invoices')->where('id', $baris->invoice_id)->value('total_amount'))->toBe(2000000);

    $this->post("/orthodontics/payment-plan/{$cicilan}/charge", ['encounter_id' => $s['kunjungan']])->assertSessionHas('error_code', 'ortho.installment_not_open');
    expect(DB::table('invoice_items')->where('source_type', 'ortho')->count())->toBe(1);
    $this->get('/orthodontics')->assertInertia(fn ($p) => $p->where('cases.0.unbilled_amount', 4000000));
});

it('P2-04 cicilan hanya dapat ditagihkan ke kunjungan terbuka milik pasien yang sama', function () {
    $s = siapOrtho();
    buatPaketOrtho($s);
    $cicilan = (string) DB::table('orthodontic_installments')->orderBy('seq')->value('id');
    $lain = siapKunjungan();

    $this->actingAs($s['kasir'])->post("/orthodontics/payment-plan/{$cicilan}/charge", ['encounter_id' => $lain['kunjungan']])->assertSessionHas('error_code', 'ortho.encounter_invalid');
    $this->post("/orthodontics/payment-plan/{$cicilan}/charge", ['encounter_id' => strtoupper((string) Str::ulid())])->assertSessionHas('error_code', 'ortho.encounter_invalid');
    $this->post("/orthodontics/payment-plan/{$cicilan}/charge", [])->assertSessionHasErrors('encounter_id');
    expect(DB::table('invoice_items')->where('source_type', 'ortho')->count())->toBe(0);

    $this->actingAs($s['dokter_user'])->post("/orthodontics/payment-plan/{$cicilan}/charge", ['encounter_id' => $s['kunjungan']])->assertForbidden();
});

it('P2-04 tagihan kunjungan yang sudah terbit menolak cicilan baru', function () {
    $s = siapOrtho();
    buatPaketOrtho($s);
    $ids = DB::table('orthodontic_installments')->orderBy('seq')->pluck('id')->all();
    $this->actingAs($s['kasir'])->post("/orthodontics/payment-plan/{$ids[0]}/charge", ['encounter_id' => $s['kunjungan']])->assertSessionHasNoErrors();
    DB::table('invoices')->where('encounter_id', $s['kunjungan'])->update(['lifecycle' => 'issued', 'status' => 'unpaid']);

    $this->post("/orthodontics/payment-plan/{$ids[1]}/charge", ['encounter_id' => $s['kunjungan']])->assertSessionHas('error_code', 'invoice.not_editable');
    expect(DB::table('orthodontic_installments')->where('id', $ids[1])->value('status'))->toBe('scheduled');
});

it('P2-04 menghentikan perawatan membatalkan cicilan yang belum ditagihkan dan menolak kontrol baru', function () {
    $s = siapOrtho();
    buatPaketOrtho($s);
    $id = idPaketOrtho();
    $pertama = (string) DB::table('orthodontic_installments')->orderBy('seq')->value('id');
    $this->actingAs($s['kasir'])->post("/orthodontics/payment-plan/{$pertama}/charge", ['encounter_id' => $s['kunjungan']]);

    $this->actingAs($s['dokter_user'])->post("/orthodontics/cases/{$id}/stop", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post("/orthodontics/cases/{$id}/stop", ['reason' => 'Pasien pindah kota'])->assertSessionHasNoErrors();

    expect(DB::table('orthodontic_cases')->value('status'))->toBe('stopped')->and(DB::table('orthodontic_installments')->orderBy('seq')->pluck('status')->all())->toBe(['invoiced', 'cancelled', 'cancelled']);
    $this->actingAs($s['perawat'])->post("/orthodontics/cases/{$id}/controls", ['control_date' => '2025-06-16', 'notes' => 'x'])->assertSessionHas('error_code', 'ortho.not_active');
    $this->actingAs($s['dokter_user'])->post("/orthodontics/cases/{$id}/complete")->assertSessionHas('error_code', 'ortho.not_active');
});

it('P2-04 menyelesaikan perawatan menutup paket tetapi cicilan sisa tetap dapat ditagihkan', function () {
    $s = siapOrtho();
    buatPaketOrtho($s);
    $id = idPaketOrtho();

    $this->post("/orthodontics/cases/{$id}/complete", ['note' => 'Hasil sesuai rencana'])->assertSessionHasNoErrors();

    expect(DB::table('orthodontic_cases')->value('status'))->toBe('completed')->and(DB::table('orthodontic_cases')->value('ended_note'))->toBe('Hasil sesuai rencana');
    $kedua = (string) DB::table('orthodontic_installments')->orderBy('seq')->value('id');
    $this->actingAs($s['kasir'])->post("/orthodontics/payment-plan/{$kedua}/charge", ['encounter_id' => $s['kunjungan']])->assertSessionHasNoErrors();
});

it('P2-04 daftar menandai kontrol terlambat dan paket klinik lain tidak terlihat', function () {
    $s = siapOrtho();
    buatPaketOrtho($s);
    $id = idPaketOrtho();
    $this->actingAs($s['dokter_user'])->get('/orthodontics')->assertInertia(fn ($p) => $p->where('cases.0.next_control_date', '2025-07-16')->where('cases.0.control_overdue', false));

    $this->actingAs($s['perawat'])->post("/orthodontics/cases/{$id}/controls", ['control_date' => '2025-06-16', 'notes' => 'x', 'next_control_date' => '2025-06-30']);
    Carbon::setTestNow(Carbon::parse('2025-07-05 10:00:00', 'Asia/Jakarta'));
    $this->flushSession();
    $this->actingAs($s['dokter_user'])->get('/orthodontics')->assertInertia(fn ($p) => $p->where('cases.0.next_control_date', '2025-06-30')->where('cases.0.control_overdue', true)->where('cases.0.last_control_date', '2025-06-16'));

    $lain = makeClinic('Klinik Lain');
    $this->actingAs(makeUser(Role::Dokter, $lain))->get('/orthodontics')->assertInertia(fn ($p) => $p->has('cases', 0));
    $this->get("/orthodontics/cases/{$id}")->assertNotFound();
});

it('P2-04 penggabungan pasien memindahkan paket ortodonti ke pasien utama', function () {
    $s = siapOrtho();
    buatPaketOrtho($s);
    $utama = makePatient($s['klinik'], 'Pasien Utama');

    $this->actingAs($s['admin'])->post("/patients/{$utama}/merge", ['source_id' => $s['pasien'], 'reason' => 'Terdaftar dua kali', 'confirm' => true])->assertSessionHasNoErrors();

    expect(DB::table('orthodontic_cases')->value('patient_id'))->toBe($utama);
});
