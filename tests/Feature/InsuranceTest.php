<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Billing\Infrastructure\Eloquent\InvoiceModel;
use Src\Clinic\Infrastructure\Eloquent\PaymentMethodModel;
use Src\IdentityAccess\Domain\Role;
use Src\Insurance\Infrastructure\Eloquent\InsurerModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Tagihan terbit Rp 500.000 untuk pasien, penjamin aktif, dan peran yang dibutuhkan.
 *
 * @return array<string, mixed>
 */
function siapKlaim(): array
{
    $s = siapKunjungan();
    foreach (['kasir' => Role::Kasir, 'admin' => Role::Admin, 'pemilik' => Role::Pemilik, 'resepsionis' => Role::Resepsionis] as $kunci => $peran) {
        $s[$kunci] = makeUser($peran, $s['klinik']);
    }
    tenant()->set($s['klinik']->id);
    $metode = new PaymentMethodModel;
    $metode->forceFill(['name' => 'Transfer', 'type' => 'transfer', 'is_active' => true])->save();
    $s['metode'] = $metode;
    $s['penjamin'] = penjaminUji($s, 'Asuransi Sehat');
    $s['tindakan'] = tindakan($s, ['default_price_amount' => 250000]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $s['tindakan']->id, 'tooth_fdi' => 46, 'quantity' => 2])->assertSessionHasNoErrors();
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $s['invoice'] = InvoiceModel::query()->where('encounter_id', $s['kunjungan'])->firstOrFail()->id;

    return $s;
}

function penjaminUji(array $s, string $nama, string $jenis = 'insurance', bool $aktif = true): InsurerModel
{
    tenant()->set($s['klinik']->id);
    $p = new InsurerModel;
    $p->forceFill(['name' => $nama, 'type' => $jenis, 'is_active' => $aktif])->save();

    return $p;
}

/** @param array<string, mixed> $o */
function polisUji(array $s, array $o = []): string
{
    test()->actingAs($s['resepsionis'])->post("/patients/{$s['pasien']}/insurance", [
        'insurer_id' => $s['penjamin']->id, 'policy_no' => 'POL-001', 'ceiling_amount' => 1000000, 'valid_from' => '2025-01-01', 'valid_to' => '2025-12-31', ...$o,
    ])->assertSessionHasNoErrors();

    return (string) DB::table('patient_policies')->orderByDesc('created_at')->orderByDesc('id')->value('id');
}

/** @param array<string, mixed> $o */
function buatKlaim(array $s, array $o = [])
{
    return test()->actingAs($s['kasir'])->post('/insurance/claims', [
        'invoice_id' => $s['invoice'], 'insurer_id' => $s['penjamin']->id, 'claimed_amount' => 500000, ...$o,
    ]);
}

function idKlaim(): string
{
    return (string) DB::table('insurance_claims')->orderByDesc('created_at')->orderByDesc('id')->value('id');
}

it('P2-02 Admin mengelola master penjamin dan Kasir hanya melihat', function () {
    $s = siapKlaim();

    $this->actingAs($s['admin'])->post('/settings/master/insurers', ['name' => 'PT Maju Jaya', 'type' => 'corporate', 'contact_email' => 'hrd@majujaya.test'])->assertSessionHasNoErrors();
    expect(DB::table('insurers')->where('name', 'PT Maju Jaya')->where('clinic_id', $s['klinik']->id)->exists())->toBeTrue();
    $this->post('/settings/master/insurers', ['name' => 'X', 'type' => 'lain'])->assertSessionHasErrors('type');

    $this->actingAs($s['kasir'])->get('/settings/master/insurers')->assertOk();
    $this->post('/settings/master/insurers', ['name' => 'Y', 'type' => 'insurance'])->assertForbidden();
});

it('P2-02 polis pasien menyimpan plafon dan masa berlaku, tercatat di audit', function () {
    $s = siapKlaim();
    $polis = polisUji($s);

    $this->get("/patients/{$s['pasien']}/insurance")->assertOk()->assertInertia(fn ($p) => $p->component('Patients/Insurance')
        ->where('policies.0.policy_no', 'POL-001')->where('policies.0.ceiling_amount', 1000000)->where('policies.0.remaining_amount', 1000000)
        ->where('policies.0.valid_now', true)->has('insurers', 1)->where('canManage', true));
    $audit = DB::table('audit_logs')->where('action', 'insurance.policy_created')->first();
    expect($audit)->not->toBeNull()->and($audit->after)->toContain('1000000');

    $this->put("/patients/{$s['pasien']}/insurance/{$polis}", ['insurer_id' => $s['penjamin']->id, 'policy_no' => 'POL-001B', 'ceiling_amount' => '', 'valid_from' => '', 'valid_to' => ''])->assertSessionHasNoErrors();
    $baris = DB::table('patient_policies')->first();
    expect($baris->policy_no)->toBe('POL-001B')->and($baris->ceiling_amount)->toBeNull();

    $this->post("/patients/{$s['pasien']}/insurance/{$polis}/deactivate")->assertSessionHasNoErrors();
    expect((bool) DB::table('patient_policies')->value('is_active'))->toBeFalse();
    $this->post("/patients/{$s['pasien']}/insurance/{$polis}/activate");
    expect((bool) DB::table('patient_policies')->value('is_active'))->toBeTrue();
});

it('P2-02 polis menolak penjamin nonaktif, nomor kosong, dan masa berlaku terbalik', function () {
    $s = siapKlaim();
    $nonaktif = penjaminUji($s, 'Lama', aktif: false);
    $this->actingAs($s['resepsionis']);

    $this->post("/patients/{$s['pasien']}/insurance", ['insurer_id' => $nonaktif->id, 'policy_no' => 'A'])->assertSessionHas('error_code', 'insurer.not_found');
    $this->post("/patients/{$s['pasien']}/insurance", ['insurer_id' => $s['penjamin']->id, 'policy_no' => ''])->assertSessionHasErrors('policy_no');
    $this->post("/patients/{$s['pasien']}/insurance", ['insurer_id' => $s['penjamin']->id, 'policy_no' => 'A', 'valid_from' => '2025-06-10', 'valid_to' => '2025-06-01'])->assertSessionHasErrors('valid_to');
    expect(DB::table('patient_policies')->count())->toBe(0);
});

it('P2-02 polis hanya diubah pemegang izin ubah pasien dan dilihat pemegang izin lihat pasien', function () {
    $s = siapKlaim();

    $this->actingAs($s['kasir'])->get("/patients/{$s['pasien']}/insurance")->assertOk()->assertInertia(fn ($p) => $p->where('canManage', false));
    $this->post("/patients/{$s['pasien']}/insurance", ['insurer_id' => $s['penjamin']->id, 'policy_no' => 'A'])->assertForbidden();
    $this->actingAs($s['dokter_user'])->post("/patients/{$s['pasien']}/insurance", ['insurer_id' => $s['penjamin']->id, 'policy_no' => 'A'])->assertForbidden();
});

it('P2-02 membuat klaim dari tagihan terbit memberi nomor KLM dan mencatat audit', function () {
    $s = siapKlaim();

    buatKlaim($s, ['notes' => 'Klaim rawat jalan gigi'])->assertSessionHasNoErrors();

    $klaim = DB::table('insurance_claims')->first();
    expect($klaim->claim_no)->toBe('KLM-2025-00001')->and($klaim->status)->toBe('draft')->and($klaim->claimed_amount)->toBe(500000)->and($klaim->patient_id)->toBe($s['pasien'])
        ->and(DB::table('audit_logs')->where('action', 'insurance.claim_created')->count())->toBe(1);
    buatKlaim($s, ['claimed_amount' => 1])->assertSessionHas('error_code', 'claim.exceeds_invoice');
});

it('P2-02 klaim ditolak untuk tagihan draft, jumlah berlebih, atau penjamin nonaktif', function () {
    $s = siapKlaim();
    $draft = DB::table('invoices')->where('id', $s['invoice'])->first();
    DB::table('invoices')->where('id', $s['invoice'])->update(['lifecycle' => 'draft', 'status' => 'draft']);
    buatKlaim($s)->assertSessionHas('error_code', 'claim.invoice_not_claimable');
    DB::table('invoices')->where('id', $s['invoice'])->update(['lifecycle' => $draft->lifecycle, 'status' => $draft->status]);

    buatKlaim($s, ['claimed_amount' => 500001])->assertSessionHas('error_code', 'claim.exceeds_invoice');
    buatKlaim($s, ['claimed_amount' => 0])->assertSessionHasErrors('claimed_amount');
    $nonaktif = penjaminUji($s, 'Tutup', aktif: false);
    buatKlaim($s, ['insurer_id' => $nonaktif->id])->assertSessionHas('error_code', 'insurer.not_found');
    expect(DB::table('insurance_claims')->count())->toBe(0);
});

it('P2-02 klaim dengan polis memeriksa pemilik, penjamin, masa berlaku, dan sisa plafon', function () {
    $s = siapKlaim();
    $polis = polisUji($s, ['ceiling_amount' => 300000]);
    $lain = penjaminUji($s, 'Asuransi Lain');
    $pasienLain = makePatient($s['klinik'], 'Pasien Lain');
    DB::table('patient_policies')->insert(['id' => strtoupper((string) Str::ulid()), 'clinic_id' => $s['klinik']->id, 'patient_id' => $pasienLain, 'insurer_id' => $s['penjamin']->id, 'policy_no' => 'LAIN', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $polisLain = (string) DB::table('patient_policies')->where('patient_id', $pasienLain)->value('id');

    buatKlaim($s, ['policy_id' => $polis, 'claimed_amount' => 300001])->assertSessionHas('error_code', 'claim.ceiling_exceeded');
    buatKlaim($s, ['policy_id' => $polis, 'insurer_id' => $lain->id, 'claimed_amount' => 100000])->assertSessionHas('error_code', 'claim.policy_mismatch');
    buatKlaim($s, ['policy_id' => $polisLain, 'claimed_amount' => 100000])->assertSessionHas('error_code', 'claim.policy_mismatch');
    DB::table('patient_policies')->where('id', $polis)->update(['valid_to' => '2025-06-01']);
    buatKlaim($s, ['policy_id' => $polis, 'claimed_amount' => 100000])->assertSessionHas('error_code', 'claim.policy_expired');
    DB::table('patient_policies')->where('id', $polis)->update(['valid_to' => '2025-12-31']);

    buatKlaim($s, ['policy_id' => $polis, 'claimed_amount' => 200000])->assertSessionHasNoErrors();
    buatKlaim($s, ['policy_id' => $polis, 'claimed_amount' => 100001])->assertSessionHas('error_code', 'claim.ceiling_exceeded');
    $this->get("/patients/{$s['pasien']}/insurance")->assertInertia(fn ($p) => $p->where('policies.0.used_amount', 200000)->where('policies.0.remaining_amount', 100000));
});

it('P2-02 klaim ditolak atau dibatalkan tidak lagi menghabiskan plafon', function () {
    $s = siapKlaim();
    $polis = polisUji($s, ['ceiling_amount' => 300000]);
    buatKlaim($s, ['policy_id' => $polis, 'claimed_amount' => 300000]);
    $id = idKlaim();

    $this->post("/insurance/claims/{$id}/cancel")->assertSessionHasNoErrors();
    expect(DB::table('insurance_claims')->value('status'))->toBe('cancelled');
    buatKlaim($s, ['policy_id' => $polis, 'claimed_amount' => 300000])->assertSessionHasNoErrors();
    $kedua = idKlaim();
    $this->post("/insurance/claims/{$kedua}/submit");
    $this->post("/insurance/claims/{$kedua}/reject", ['reason' => 'Di luar manfaat'])->assertSessionHasNoErrors();
    buatKlaim($s, ['policy_id' => $polis, 'claimed_amount' => 300000])->assertSessionHasNoErrors();
});

it('P2-02 alur penuh: ajukan, setujui sebagian, terima bertahap, dan pembayaran masuk ke tagihan', function () {
    $s = siapKlaim();
    buatKlaim($s);
    $id = idKlaim();

    $this->post("/insurance/claims/{$id}/submit")->assertSessionHasNoErrors();
    $this->post("/insurance/claims/{$id}/approve", ['approved_amount' => 400000, 'note' => 'Plafon manfaat'])->assertSessionHasNoErrors();
    $terima = fn (int $jumlah, string $kunci) => $this->post("/insurance/claims/{$id}/receive", ['amount' => $jumlah, 'payment_method_id' => $s['metode']->id, 'reference_no' => 'TRF-1', 'idempotency_key' => $kunci]);

    $terima(150000, 'k1')->assertSessionHasNoErrors();
    $klaim = DB::table('insurance_claims')->first();
    expect($klaim->status)->toBe('approved')->and($klaim->paid_amount)->toBe(150000);
    $faktur = DB::table('invoices')->where('id', $s['invoice'])->first();
    expect($faktur->paid_amount)->toBe(150000)->and($faktur->status)->toBe('partial');
    expect(DB::table('payments')->where('invoice_id', $s['invoice'])->where('payment_method_id', $s['metode']->id)->sum('amount'))->toEqual(150000);

    $terima(250000, 'k2')->assertSessionHasNoErrors();
    expect(DB::table('insurance_claims')->value('status'))->toBe('paid')->and(DB::table('insurance_claims')->value('paid_at'))->not->toBeNull()
        ->and(DB::table('invoices')->where('id', $s['invoice'])->value('paid_amount'))->toBe(400000)
        ->and(DB::table('insurance_claim_receipts')->count())->toBe(2);
    expect(DB::table('audit_logs')->whereIn('action', ['insurance.claim_submitted', 'insurance.claim_approved', 'insurance.claim_received'])->count())->toBe(4);
});

it('P2-02 pencatatan uang dari penjamin idempoten terhadap kunci dan tidak melebihi sisa yang disetujui', function () {
    $s = siapKlaim();
    buatKlaim($s);
    $id = idKlaim();
    $this->post("/insurance/claims/{$id}/submit");
    $this->post("/insurance/claims/{$id}/approve", ['approved_amount' => 200000]);
    $terima = fn (int $jumlah, string $kunci) => $this->post("/insurance/claims/{$id}/receive", ['amount' => $jumlah, 'payment_method_id' => $s['metode']->id, 'idempotency_key' => $kunci]);

    $terima(100000, 'sama')->assertSessionHasNoErrors();
    $terima(100000, 'sama')->assertSessionHasNoErrors();
    expect(DB::table('insurance_claims')->value('paid_amount'))->toBe(100000)->and(DB::table('payments')->where('invoice_id', $s['invoice'])->count())->toBe(1);

    $terima(100001, 'lain')->assertSessionHas('error_code', 'claim.overpaid');
    expect(DB::table('insurance_claims')->value('paid_amount'))->toBe(100000)->and(DB::table('payments')->count())->toBe(1);
});

it('P2-02 pembayaran penjamin yang ditolak tagihan membatalkan seluruh pencatatan', function () {
    $s = siapKlaim();
    buatKlaim($s);
    $id = idKlaim();
    $this->post("/insurance/claims/{$id}/submit");
    $this->post("/insurance/claims/{$id}/approve", ['approved_amount' => 500000]);
    tenant()->set($s['klinik']->id);
    $nonaktif = new PaymentMethodModel;
    $nonaktif->forceFill(['name' => 'Lama', 'type' => 'cash', 'is_active' => false])->save();

    $this->post("/insurance/claims/{$id}/receive", ['amount' => 100000, 'payment_method_id' => $nonaktif->id, 'idempotency_key' => 'x'])->assertSessionHas('error_code', 'payment.method_not_found');

    expect(DB::table('insurance_claims')->value('paid_amount'))->toBe(0)->and(DB::table('insurance_claim_receipts')->count())->toBe(0)->and(DB::table('payments')->count())->toBe(0);
});

it('P2-02 klaim tidak dapat menerima uang sebelum disetujui atau setelah ditolak', function () {
    $s = siapKlaim();
    buatKlaim($s);
    $id = idKlaim();
    $terima = fn () => $this->post("/insurance/claims/{$id}/receive", ['amount' => 1000, 'payment_method_id' => $s['metode']->id, 'idempotency_key' => 'a']);

    $terima()->assertSessionHas('error_code', 'claim.not_approved');
    $this->post("/insurance/claims/{$id}/approve", ['approved_amount' => 1000])->assertSessionHas('error_code', 'claim.not_submitted');
    $this->post("/insurance/claims/{$id}/submit");
    $this->post("/insurance/claims/{$id}/reject", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post("/insurance/claims/{$id}/reject", ['reason' => 'Polis kedaluwarsa'])->assertSessionHasNoErrors();
    $terima()->assertSessionHas('error_code', 'claim.not_approved');
    expect(DB::table('insurance_claims')->value('decision_note'))->toBe('Polis kedaluwarsa');
});

it('P2-02 piutang penjamin per penjamin dengan umur dari tanggal pengajuan', function () {
    $s = siapKlaim();
    buatKlaim($s, ['claimed_amount' => 200000]);
    $a = idKlaim();
    $this->post("/insurance/claims/{$a}/submit");
    Carbon::setTestNow(now()->addDays(45));
    $this->flushSession();
    buatKlaim($s, ['claimed_amount' => 100000]);
    $b = idKlaim();
    $this->post("/insurance/claims/{$b}/submit");
    $this->post("/insurance/claims/{$b}/approve", ['approved_amount' => 80000]);

    $this->actingAs($s['admin'])->get('/insurance/receivables')->assertOk()->assertInertia(fn ($p) => $p->component('Insurance/Receivables')
        ->where('total', 280000)->where('count', 2)
        ->where('buckets.0.amount', 80000)->where('buckets.1.amount', 200000)->where('buckets.2.amount', 0)
        ->where('insurers.0.insurer_name', 'Asuransi Sehat')->where('insurers.0.outstanding', 280000));
});

it('P2-02 piutang turun saat uang diterima dan lunas tidak lagi tampil', function () {
    $s = siapKlaim();
    buatKlaim($s, ['claimed_amount' => 100000]);
    $id = idKlaim();
    $this->post("/insurance/claims/{$id}/submit");
    $this->post("/insurance/claims/{$id}/approve", ['approved_amount' => 100000]);
    $this->post("/insurance/claims/{$id}/receive", ['amount' => 40000, 'payment_method_id' => $s['metode']->id, 'idempotency_key' => '1']);

    $this->actingAs($s['pemilik'])->get('/insurance/receivables')->assertInertia(fn ($p) => $p->where('total', 60000));

    $this->actingAs($s['kasir'])->post("/insurance/claims/{$id}/receive", ['amount' => 60000, 'payment_method_id' => $s['metode']->id, 'idempotency_key' => '2']);
    $this->actingAs($s['pemilik'])->get('/insurance/receivables')->assertInertia(fn ($p) => $p->where('total', 0)->where('count', 0)->has('insurers', 0));
});

it('P2-02 izin: kasir mengelola klaim, resepsionis hanya melihat, piutang hanya untuk keuangan penuh', function () {
    $s = siapKlaim();
    buatKlaim($s)->assertSessionHasNoErrors();
    $id = idKlaim();

    $this->actingAs($s['resepsionis'])->get('/insurance/claims')->assertOk();
    $this->get("/insurance/claims/{$id}")->assertOk();
    $this->post('/insurance/claims', ['invoice_id' => $s['invoice'], 'insurer_id' => $s['penjamin']->id, 'claimed_amount' => 1])->assertForbidden();
    $this->post("/insurance/claims/{$id}/submit")->assertForbidden();
    $this->get('/insurance/receivables')->assertForbidden();

    $this->actingAs($s['kasir'])->get('/insurance/receivables')->assertForbidden();
    $this->actingAs($s['dokter_user'])->post("/insurance/claims/{$id}/receive", ['amount' => 1, 'payment_method_id' => $s['metode']->id, 'idempotency_key' => 'z'])->assertForbidden();
});

it('P2-02 membuka klaim tercatat di jejak akses dan klinik lain tidak melihatnya', function () {
    $s = siapKlaim();
    buatKlaim($s);
    $id = idKlaim();

    $this->get("/insurance/claims/{$id}")->assertOk()->assertInertia(fn ($p) => $p->component('Insurance/Claims/Show')->where('claim.claim_no', 'KLM-2025-00001')->where('can.submit', true)->where('can.receive', false));
    expect(DB::table('access_logs')->where('patient_id', $s['pasien'])->where('route', 'insurance.claims.show')->exists())->toBeTrue();

    $lain = makeClinic('Klinik Lain');
    $this->actingAs(makeUser(Role::Kasir, $lain))->get("/insurance/claims/{$id}")->assertNotFound();
    $this->get('/insurance/claims')->assertInertia(fn ($p) => $p->has('claims', 0));
});

it('P2-02 formulir klaim menampilkan sisa tagihan yang dapat diklaim dan polis berlaku', function () {
    $s = siapKlaim();
    polisUji($s);
    buatKlaim($s, ['claimed_amount' => 150000]);

    $this->get("/insurance/claims/create?invoice={$s['invoice']}")->assertOk()->assertInertia(fn ($p) => $p->component('Insurance/Claims/Create')
        ->where('invoice.claimable_amount', 350000)->where('invoice.committed_amount', 150000)->where('invoice.claimable', true)->has('policies', 1)->has('insurers', 1));
    $this->get('/insurance/claims/create')->assertSessionHasErrors('invoice');
});
