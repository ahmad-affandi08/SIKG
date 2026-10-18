<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Src\Billing\Infrastructure\Eloquent\InvoiceModel;
use Src\Clinic\Infrastructure\Eloquent\PaymentMethodModel;
use Src\IdentityAccess\Domain\Role;

const KUNCI_SERVER = 'SB-Mid-server-uji-123';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
    test()->withoutMiddleware(ThrottleRequests::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Tagihan terbit Rp 500.000 (2 x Rp 250.000) dengan pembayaran online aktif (metode QRIS).
 *
 * @return array<string, mixed>
 */
function siapDaring(bool $aktif = true): array
{
    $s = siapKunjungan();
    $s['kasir'] = makeUser(Role::Kasir, $s['klinik']);
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    tenant()->set($s['klinik']->id);
    $qris = new PaymentMethodModel;
    $qris->forceFill(['name' => 'QRIS', 'type' => 'qris', 'is_active' => true])->save();
    $tunai = new PaymentMethodModel;
    $tunai->forceFill(['name' => 'Tunai', 'type' => 'cash', 'is_active' => true])->save();
    $s['qris'] = $qris;
    $s['tunai'] = $tunai;
    $tindakan = tindakan($s, ['default_price_amount' => 250000]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $tindakan->id, 'tooth_fdi' => 46, 'quantity' => 2])->assertSessionHasNoErrors();
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $s['invoice'] = InvoiceModel::query()->where('encounter_id', $s['kunjungan'])->firstOrFail()->id;

    if ($aktif) {
        test()->actingAs($s['admin'])->post('/settings/online-payments', [
            'enabled' => true, 'environment' => 'sandbox', 'merchant_id' => 'G-UJI', 'server_key' => KUNCI_SERVER, 'payment_method_id' => $qris->id, 'expiry_minutes' => 15,
        ])->assertSessionHasNoErrors();
        tenant()->set($s['klinik']->id);
        $s['token'] = (string) DB::table('online_payment_settings')->where('clinic_id', $s['klinik']->id)->value('webhook_token');
    }

    return $s;
}

/** Penyedia palsu: `$status` menentukan jawaban status; semua panggilan terekam oleh Http::fake. */
function pasangPenyedia(string $status = 'pending', string $gross = '500000.00'): object
{
    $kondisi = new class
    {
        public string $status = 'pending';

        public string $gross = '500000.00';

        public bool $gagal = false;
    };
    $kondisi->status = $status;
    $kondisi->gross = $gross;
    Http::fake(function (Request $request) use ($kondisi) {
        $url = $request->url();
        if (str_ends_with($url, '/v2/charge')) {
            if ($kondisi->gagal) {
                return Http::response(['status_code' => '500'], 500);
            }

            return Http::response([
                'status_code' => '201', 'transaction_id' => 'TX-UJI-1', 'qr_string' => '00020101021226UJI', 'transaction_status' => 'pending',
                'actions' => [['name' => 'generate-qr-code', 'url' => 'https://api.sandbox.midtrans.com/v2/gopay/TX-UJI-1/qr-code']],
            ], 201);
        }
        if (str_contains($url, '/qr-code')) {
            return Http::response('PNG-UJI', 200, ['Content-Type' => 'image/png']);
        }
        if (str_ends_with($url, '/status')) {
            return Http::response(['status_code' => '200', 'transaction_id' => 'TX-UJI-1', 'transaction_status' => $kondisi->status, 'fraud_status' => 'accept', 'gross_amount' => $kondisi->gross], 200);
        }
        if (str_ends_with($url, '/cancel')) {
            return Http::response(['status_code' => '200', 'transaction_status' => 'cancel'], 200);
        }

        return Http::response([], 404);
    });

    return $kondisi;
}

/** @return array<string, mixed> */
function badanWebhook(string $orderId, string $gross = '500000.00', string $kode = '200', string $status = 'settlement'): array
{
    return [
        'order_id' => $orderId, 'status_code' => $kode, 'gross_amount' => $gross, 'transaction_status' => $status,
        'signature_key' => hash('sha512', $orderId.$kode.$gross.KUNCI_SERVER),
    ];
}

function mulaiQr(array $s, array $data = [])
{
    return test()->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/online-payments", $data);
}

function niatDaring(): object
{
    return DB::table('payment_intents')->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
}

it('P3-05 Admin menyimpan pengaturan; server key terenkripsi, tidak pernah ditampilkan kembali, dan tidak masuk audit', function () {
    $s = siapDaring();

    $baris = DB::table('online_payment_settings')->first();
    expect($baris->server_key_encrypted)->not->toContain(KUNCI_SERVER)->and((int) $baris->enabled)->toBe(1)->and($baris->webhook_token)->toMatch('/^[a-f0-9]{40}$/')->and($baris->payment_method_id)->toBe($s['qris']->id);
    $audit = DB::table('audit_logs')->where('action', 'online_payment.settings_changed')->first();
    expect(json_encode($audit))->not->toContain(KUNCI_SERVER)->and(json_encode($audit))->toContain('key_replaced');

    $this->actingAs($s['admin'])->get('/settings/online-payments')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/OnlinePayments')
        ->where('settings.has_key', true)->where('settings.webhook_path', "/webhooks/payments/{$s['token']}")->missing('settings.server_key'));

    // Kunci kosong mempertahankan kunci lama.
    $this->post('/settings/online-payments', ['enabled' => true, 'environment' => 'production', 'server_key' => '', 'payment_method_id' => $s['qris']->id, 'expiry_minutes' => 30])->assertSessionHasNoErrors();
    expect(DB::table('online_payment_settings')->value('server_key_encrypted'))->toBe($baris->server_key_encrypted)->and(DB::table('online_payment_settings')->value('environment'))->toBe('production');
});

it('P3-05 pengaturan menolak aktivasi tanpa kunci atau metode, lingkungan salah, dan pengguna tanpa izin', function () {
    $s = siapDaring(false);
    $this->actingAs($s['admin'])->post('/settings/online-payments', ['enabled' => true, 'environment' => 'sandbox', 'payment_method_id' => $s['qris']->id, 'expiry_minutes' => 15])->assertSessionHas('error_code', 'online.settings_incomplete');
    $this->post('/settings/online-payments', ['enabled' => true, 'environment' => 'sandbox', 'server_key' => 'k', 'expiry_minutes' => 15])->assertSessionHas('error_code', 'online.settings_incomplete');
    $this->post('/settings/online-payments', ['enabled' => false, 'environment' => 'demo', 'expiry_minutes' => 15])->assertSessionHasErrors('environment');
    $this->post('/settings/online-payments', ['enabled' => false, 'environment' => 'sandbox', 'expiry_minutes' => 2])->assertSessionHasErrors('expiry_minutes');
    $this->post('/settings/online-payments', ['enabled' => false, 'environment' => 'sandbox', 'payment_method_id' => str_repeat('0', 26), 'expiry_minutes' => 15])->assertSessionHas('error_code', 'payment.method_not_found');

    $this->actingAs($s['kasir'])->get('/settings/online-payments')->assertForbidden();
    $this->post('/settings/online-payments', [])->assertForbidden();
    mulaiQr($s)->assertSessionHas('error_code', 'online.not_ready');
});

it('P3-05 kasir membuat QRIS untuk sisa tagihan; pemanggilan ke penyedia memakai kunci klinik dan jumlah rupiah bulat', function () {
    $s = siapDaring();
    pasangPenyedia();

    mulaiQr($s)->assertSessionHasNoErrors()->assertRedirect("/invoices/{$s['invoice']}/online-payments");

    $niat = niatDaring();
    expect($niat->status)->toBe('pending')->and((int) $niat->amount)->toBe(500000)->and($niat->order_id)->toBe('SIKG-'.$niat->id)->and($niat->gateway_ref)->toBe('TX-UJI-1')
        ->and($niat->qr_image)->toStartWith('data:image/png;base64,')->and($niat->qr_string)->toBe('00020101021226UJI')->and((int) $niat->active_slot)->toBe(1)->and($niat->created_by)->toBe($s['kasir']->id);
    Http::assertSent(function (Request $r) use ($niat) {
        return str_ends_with($r->url(), '/v2/charge') && str_starts_with($r->url(), 'https://api.sandbox.midtrans.com')
            && $r->header('Authorization')[0] === 'Basic '.base64_encode(KUNCI_SERVER.':')
            && $r['transaction_details']['order_id'] === $niat->order_id && $r['transaction_details']['gross_amount'] === 500000 && $r['payment_type'] === 'qris';
    });

    $this->get("/invoices/{$s['invoice']}/online-payments")->assertOk()->assertInertia(fn ($p) => $p->component('Invoices/OnlinePayment')
        ->where('ready', true)->where('active.amount', 500000)->where('active.status', 'pending')->has('active.qr_image')->where('invoice.balance', 500000));
});

it('P3-05 QR aktif dipakai ulang, jumlah lain menggantinya, dan jumlah tidak sah ditolak', function () {
    $s = siapDaring();
    pasangPenyedia();

    mulaiQr($s)->assertSessionHasNoErrors();
    mulaiQr($s)->assertSessionHasNoErrors();
    expect(DB::table('payment_intents')->count())->toBe(1);

    mulaiQr($s, ['amount' => 200000])->assertSessionHasNoErrors();
    expect(DB::table('payment_intents')->count())->toBe(2)->and(DB::table('payment_intents')->where('status', 'cancelled')->count())->toBe(1)->and(DB::table('payment_intents')->where('status', 'pending')->count())->toBe(1);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/cancel'));

    mulaiQr($s, ['amount' => 500001])->assertSessionHas('error_code', 'online.invalid_amount');
    mulaiQr($s, ['amount' => 0])->assertSessionHasErrors('amount');
    mulaiQr($s, ['amount' => 'abc'])->assertSessionHasErrors('amount');
});

it('P3-05 hanya tagihan terbit yang belum lunas yang dapat dibayar online', function () {
    $s = siapDaring();
    pasangPenyedia();
    $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/payments", ['payment_method_id' => $s['tunai']->id, 'type' => 'pay', 'amount' => 500000], ['Idempotency-Key' => 'k-tunai-1'])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);

    mulaiQr($s)->assertSessionHas('error_code', 'invoice.already_paid');
    expect(DB::table('payment_intents')->count())->toBe(0);
});

it('P3-05 webhook yang sah mencatat pembayaran lewat alur biasa dan notifikasi ganda tidak mencatat dua kali', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia();
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();
    $penyedia->status = 'settlement';
    auth()->logout();

    $this->postJson("/webhooks/payments/{$s['token']}", badanWebhook($niat->order_id))->assertOk()->assertJson(['ok' => true]);
    $this->postJson("/webhooks/payments/{$s['token']}", badanWebhook($niat->order_id))->assertOk();

    $niatSesudah = niatDaring();
    $bayar = DB::table('payments')->where('invoice_id', $s['invoice'])->get();
    expect($niatSesudah->status)->toBe('paid')->and($niatSesudah->active_slot)->toBeNull()->and($niatSesudah->paid_at)->not->toBeNull()
        ->and($bayar)->toHaveCount(1)->and((int) $bayar[0]->amount)->toBe(500000)->and($bayar[0]->payment_method_id)->toBe($s['qris']->id)->and($bayar[0]->idempotency_key)->toBe('online:'.$niat->id)
        ->and($bayar[0]->reference_no)->toBe('TX-UJI-1')->and($bayar[0]->received_by)->toBe($s['kasir']->id)->and($niatSesudah->payment_id)->toBe($bayar[0]->id);
    tenant()->set($s['klinik']->id);
    expect(InvoiceModel::query()->findOrFail($s['invoice'])->status)->toBe('paid')
        ->and(DB::table('audit_logs')->where('action', 'online_payment.paid')->count())->toBe(1);
});

it('P3-05 webhook ditolak untuk token tak dikenal dan tanda tangan salah; badan webhook bukan sumber kebenaran', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia('pending');
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();
    auth()->logout();

    $this->postJson('/webhooks/payments/'.str_repeat('a', 40), badanWebhook($niat->order_id))->assertNotFound();
    $this->postJson("/webhooks/payments/{$s['token']}", [...badanWebhook($niat->order_id), 'signature_key' => str_repeat('0', 128)])->assertForbidden();
    $this->postJson("/webhooks/payments/{$s['token']}", ['order_id' => $niat->order_id])->assertForbidden();
    $this->postJson("/webhooks/payments/{$s['token']}", badanWebhook('SIKG-TIDAK-ADA'))->assertOk();

    // Tanda tangan sah, tetapi penyedia menjawab masih menunggu: tidak ada pembayaran yang dicatat.
    $this->postJson("/webhooks/payments/{$s['token']}", badanWebhook($niat->order_id))->assertOk();
    expect(niatDaring()->status)->toBe('pending')->and(DB::table('payments')->count())->toBe(0);
    expect($penyedia->status)->toBe('pending');
});

it('P3-05 token webhook klinik lain tidak dapat menyentuh niat bayar klinik ini', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia('settlement');
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();
    $lain = siapDaring();
    auth()->logout();

    $this->postJson("/webhooks/payments/{$lain['token']}", badanWebhook($niat->order_id))->assertOk();

    expect(niatDaring()->status)->toBe('pending')->and(DB::table('payments')->count())->toBe(0)->and($penyedia->status)->toBe('settlement');
});

it('P3-05 uang yang tidak dapat dicatat ditandai Mismatch: tagihan sudah lunas tunai, atau jumlah berbeda', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia('settlement');
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();
    // Pasien membayar tunai di kasir sebelum notifikasi QR tiba.
    $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/payments", ['payment_method_id' => $s['tunai']->id, 'type' => 'pay', 'amount' => 500000], ['Idempotency-Key' => 'k-tunai-1'])->assertSessionHasNoErrors();
    auth()->logout();

    $this->postJson("/webhooks/payments/{$s['token']}", badanWebhook($niat->order_id))->assertOk();

    expect(niatDaring()->status)->toBe('mismatch')->and(DB::table('payments')->where('invoice_id', $s['invoice'])->count())->toBe(1)->and(niatDaring()->note)->toContain('lunas')
        ->and(DB::table('audit_logs')->where('action', 'online_payment.mismatch')->count())->toBe(1);

    $this->actingAs($s['kasir'])->get('/online-payments')->assertOk()->assertInertia(fn ($p) => $p->component('Invoices/OnlinePayments')->where('intents.0.status', 'mismatch')->where('intents.0.amount', 500000));
});

it('P3-05 jumlah dari penyedia yang berbeda dari QR tidak dicatat', function () {
    $s = siapDaring();
    pasangPenyedia('settlement', '400000.00');
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();
    auth()->logout();

    $this->postJson("/webhooks/payments/{$s['token']}", badanWebhook($niat->order_id, '400000.00'))->assertOk();

    expect(niatDaring()->status)->toBe('mismatch')->and(DB::table('payments')->count())->toBe(0);
});

it('P3-05 rekonsiliasi terjadwal mencatat yang sudah dibayar dan menutup yang kedaluwarsa', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia('pending');
    mulaiQr($s, ['amount' => 100000])->assertSessionHasNoErrors();
    $pertama = niatDaring();

    Artisan::call('sikg:payments:reconcile');
    expect(niatDaring()->status)->toBe('pending');

    $penyedia->status = 'settlement';
    $penyedia->gross = '100000.00';
    Artisan::call('sikg:payments:reconcile');
    expect(DB::table('payment_intents')->where('id', $pertama->id)->value('status'))->toBe('paid')->and(DB::table('payments')->count())->toBe(1);

    // QR kedua lewat masa berlaku.
    $penyedia->status = 'pending';
    mulaiQr($s, ['amount' => 50000])->assertSessionHasNoErrors();
    Carbon::setTestNow(Carbon::now()->addMinutes(20));
    Artisan::call('sikg:payments:reconcile');
    expect(niatDaring()->status)->toBe('expired')->and((int) DB::table('payment_intents')->where('status', 'pending')->count())->toBe(0);

    // Penyedia melaporkan kedaluwarsa atau ditolak.
    $penyedia->status = 'pending';
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:30:00', 'Asia/Jakarta'));
    mulaiQr($s, ['amount' => 50000])->assertSessionHasNoErrors();
    $penyedia->status = 'expire';
    Artisan::call('sikg:payments:reconcile');
    expect(niatDaring()->status)->toBe('expired');
});

it('P3-05 pembatalan tagihan membatalkan QR yang menunggu, dan pembayaran yang telanjur masuk ditandai Mismatch', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia();
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();

    $this->actingAs($s['admin'])->post("/invoices/{$s['invoice']}/void", ['reason' => 'Salah input'])->assertSessionHasNoErrors();
    expect(niatDaring()->status)->toBe('cancelled')->and(niatDaring()->active_slot)->toBeNull();
    Http::assertSent(fn (Request $r) => str_contains($r->url(), $niat->order_id.'/cancel'));

    $penyedia->status = 'settlement';
    auth()->logout();
    $this->postJson("/webhooks/payments/{$s['token']}", badanWebhook($niat->order_id))->assertOk();
    expect(niatDaring()->status)->toBe('mismatch')->and(DB::table('payments')->where('invoice_id', $s['invoice'])->count())->toBe(0);
});

it('P3-05 kasir membatalkan QR; bila ternyata sudah dibayar, pembayarannya dicatat dan pembatalan ditolak', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia();
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();

    $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/online-payments/{$niat->id}/cancel")->assertSessionHasNoErrors();
    expect(niatDaring()->status)->toBe('cancelled');
    $this->post("/invoices/{$s['invoice']}/online-payments/{$niat->id}/cancel")->assertSessionHas('error_code', 'online.not_pending');

    mulaiQr($s)->assertSessionHasNoErrors();
    $kedua = niatDaring();
    $penyedia->status = 'settlement';
    $this->post("/invoices/{$s['invoice']}/online-payments/{$kedua->id}/cancel")->assertSessionHas('error_code', 'online.already_paid_at_gateway');
    expect(niatDaring()->status)->toBe('paid')->and(DB::table('payments')->count())->toBe(1);
});

it('P3-05 galat penyedia membuat niat gagal dengan pesan bersih dari kunci', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia();
    $penyedia->gagal = true;

    $respons = mulaiQr($s);

    $respons->assertSessionHas('error_code', 'online.gateway_failed');
    expect(niatDaring()->status)->toBe('failed')->and((string) niatDaring()->note)->not->toContain(KUNCI_SERVER)->and((int) (niatDaring()->active_slot ?? 0))->toBe(0);
    // Setelah gagal, kasir dapat mencoba lagi.
    $penyedia->gagal = false;
    mulaiQr($s)->assertSessionHasNoErrors();
    expect(niatDaring()->status)->toBe('pending');
});

it('P3-05 polling status, hak akses, dan isolasi klinik', function () {
    $s = siapDaring();
    pasangPenyedia();
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();

    $this->getJson("/internal-api/invoices/{$s['invoice']}/online-payments/{$niat->id}/status")->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.paid', false);
    $this->getJson("/internal-api/invoices/{$s['invoice']}/online-payments/".str_repeat('0', 26).'/status')->assertStatus(422);

    $this->actingAs($s['dokter_user'])->post("/invoices/{$s['invoice']}/online-payments", [])->assertForbidden();
    $this->actingAs($s['dokter_user'])->get("/invoices/{$s['invoice']}/online-payments")->assertForbidden();
    auth()->logout();
    $this->get('/online-payments')->assertRedirect('/login');

    $lain = siapDaring();
    $this->actingAs($lain['kasir'])->get('/online-payments')->assertOk()->assertInertia(fn ($p) => $p->where('intents', []));
    $this->post("/invoices/{$s['invoice']}/online-payments/{$niat->id}/cancel")->assertSessionHas('error_code', 'online.not_found');
    expect(niatDaring()->status)->toBe('pending');
});
