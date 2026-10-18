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

const CLIENT_ID_UJI = 'BRN-0214-UJI';
const KUNCI_RAHASIA = 'SK-doku-rahasia-uji-123';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
    test()->withoutMiddleware(ThrottleRequests::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Tagihan terbit Rp 500.000 (2 x Rp 250.000) dengan pembayaran online DOKU aktif (metode QRIS).
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
            'enabled' => true, 'environment' => 'sandbox', 'client_id' => CLIENT_ID_UJI, 'secret_key' => KUNCI_RAHASIA, 'payment_method_id' => $qris->id, 'expiry_minutes' => 15,
        ])->assertSessionHasNoErrors();
        tenant()->set($s['klinik']->id);
        $s['token'] = (string) DB::table('online_payment_settings')->where('clinic_id', $s['klinik']->id)->value('webhook_token');
    }

    return $s;
}

/** Tanda tangan DOKU non-SNAP sesuai dokumentasi: HMAC-SHA256 base64 atas baris Nama:nilai. */
function tandaTanganDoku(string $rahasia, string $clientId, string $requestId, string $waktu, string $target, ?string $isi): string
{
    $baris = ["Client-Id:{$clientId}", "Request-Id:{$requestId}", "Request-Timestamp:{$waktu}", "Request-Target:{$target}"];
    if ($isi !== null) {
        $baris[] = 'Digest:'.base64_encode(hash('sha256', $isi, true));
    }

    return 'HMACSHA256='.base64_encode(hash_hmac('sha256', implode("\n", $baris), $rahasia, true));
}

/** Penyedia palsu: `$status` (status DOKU) menentukan jawaban Check Status; semua panggilan terekam oleh Http::fake. */
function pasangPenyedia(string $status = 'PENDING', string $jumlah = '500000'): object
{
    $kondisi = new class
    {
        public string $status = 'PENDING';

        public string $jumlah = '500000';

        public bool $gagal = false;

        public bool $adaPercobaan = true;
    };
    $kondisi->status = $status;
    $kondisi->jumlah = $jumlah;
    Http::fake(function (Request $request) use ($kondisi) {
        $url = $request->url();
        if (str_ends_with($url, '/checkout/v1/payment')) {
            if ($kondisi->gagal) {
                return Http::response(['error_messages' => ['x']], 500);
            }

            return Http::response([
                'message' => ['SUCCESS'],
                'response' => ['payment' => ['token_id' => 'TOK-UJI-1', 'url' => 'https://sandbox.doku.com/checkout-link-v2/TOK-UJI-1', 'expired_date' => '20250616080000', 'payment_due_date' => 15]],
            ], 200);
        }
        if (str_contains($url, '/orders/v1/status/')) {
            if (! $kondisi->adaPercobaan) {
                return Http::response(['error_messages' => ['not found']], 404);
            }

            return Http::response(['order' => ['invoice_number' => basename($url), 'amount' => $kondisi->jumlah], 'transaction' => ['status' => $kondisi->status, 'original_request_id' => 'REQ-UJI-1']], 200);
        }

        return Http::response([], 404);
    });

    return $kondisi;
}

/**
 * Mengirim notifikasi DOKU bertanda tangan ke alamat webhook klinik.
 *
 * @param  array<string, mixed>  $o
 */
function kirimWebhook(string $token, string $orderId, array $o = [])
{
    $alamat = $o['alamat'] ?? "/webhooks/payments/{$token}";
    $isi = json_encode(['order' => ['invoice_number' => $orderId, 'amount' => 500000], 'transaction' => ['status' => 'SUCCESS', 'date' => '2025-06-16T00:00:00Z']], JSON_THROW_ON_ERROR);
    $clientId = $o['client_id'] ?? CLIENT_ID_UJI;
    $waktu = '2025-06-16T00:00:01Z';
    $tanda = $o['signature'] ?? tandaTanganDoku($o['rahasia'] ?? KUNCI_RAHASIA, $clientId, 'req-webhook-1', $waktu, "/webhooks/payments/{$token}", $isi);

    return test()->call('POST', $alamat, [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_CLIENT_ID' => $clientId, 'HTTP_REQUEST_ID' => 'req-webhook-1', 'HTTP_REQUEST_TIMESTAMP' => $waktu, 'HTTP_SIGNATURE' => $tanda,
    ], $o['isi'] ?? $isi);
}

function mulaiQr(array $s, array $data = [])
{
    return test()->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/online-payments", $data);
}

function niatDaring(): object
{
    return DB::table('payment_intents')->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
}

it('P3-05 Admin menyimpan pengaturan DOKU; Secret Key terenkripsi, tidak pernah ditampilkan kembali, dan tidak masuk audit', function () {
    $s = siapDaring();

    $baris = DB::table('online_payment_settings')->first();
    expect($baris->secret_key_encrypted)->not->toContain(KUNCI_RAHASIA)->and((int) $baris->enabled)->toBe(1)->and($baris->webhook_token)->toMatch('/^[a-f0-9]{40}$/')->and($baris->payment_method_id)->toBe($s['qris']->id)
        ->and($baris->client_id)->toBe(CLIENT_ID_UJI)->and($baris->provider)->toBe('doku');
    $audit = DB::table('audit_logs')->where('action', 'online_payment.settings_changed')->first();
    expect(json_encode($audit))->not->toContain(KUNCI_RAHASIA)->and(json_encode($audit))->toContain('key_replaced');

    $this->actingAs($s['admin'])->get('/settings/online-payments')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/OnlinePayments')
        ->where('settings.has_key', true)->where('settings.client_id', CLIENT_ID_UJI)->where('settings.webhook_path', "/webhooks/payments/{$s['token']}")->missing('settings.secret_key'));

    // Kunci kosong mempertahankan kunci lama.
    $this->post('/settings/online-payments', ['enabled' => true, 'environment' => 'production', 'client_id' => CLIENT_ID_UJI, 'secret_key' => '', 'payment_method_id' => $s['qris']->id, 'expiry_minutes' => 30])->assertSessionHasNoErrors();
    expect(DB::table('online_payment_settings')->value('secret_key_encrypted'))->toBe($baris->secret_key_encrypted)->and(DB::table('online_payment_settings')->value('environment'))->toBe('production');
});

it('P3-05 pengaturan menolak aktivasi tanpa Client ID, Secret Key, atau metode, lingkungan salah, dan pengguna tanpa izin', function () {
    $s = siapDaring(false);
    $dasar = ['enabled' => true, 'environment' => 'sandbox', 'expiry_minutes' => 15];
    $this->actingAs($s['admin'])->post('/settings/online-payments', [...$dasar, 'client_id' => 'C', 'payment_method_id' => $s['qris']->id])->assertSessionHas('error_code', 'online.settings_incomplete');
    $this->post('/settings/online-payments', [...$dasar, 'secret_key' => 'k', 'payment_method_id' => $s['qris']->id])->assertSessionHas('error_code', 'online.settings_incomplete');
    $this->post('/settings/online-payments', [...$dasar, 'client_id' => 'C', 'secret_key' => 'k'])->assertSessionHas('error_code', 'online.settings_incomplete');
    $this->post('/settings/online-payments', ['enabled' => false, 'environment' => 'demo', 'expiry_minutes' => 15])->assertSessionHasErrors('environment');
    $this->post('/settings/online-payments', ['enabled' => false, 'environment' => 'sandbox', 'expiry_minutes' => 2])->assertSessionHasErrors('expiry_minutes');
    $this->post('/settings/online-payments', ['enabled' => false, 'environment' => 'sandbox', 'payment_method_id' => str_repeat('0', 26), 'expiry_minutes' => 15])->assertSessionHas('error_code', 'payment.method_not_found');

    $this->actingAs($s['kasir'])->get('/settings/online-payments')->assertForbidden();
    $this->post('/settings/online-payments', [])->assertForbidden();
    mulaiQr($s)->assertSessionHas('error_code', 'online.not_ready');
});

it('P3-05 kasir membuat pembayaran QRIS untuk sisa tagihan; permintaan ke DOKU bertanda tangan dan berjumlah rupiah bulat', function () {
    $s = siapDaring();
    pasangPenyedia();

    mulaiQr($s)->assertSessionHasNoErrors()->assertRedirect("/invoices/{$s['invoice']}/online-payments");

    $niat = niatDaring();
    expect($niat->status)->toBe('pending')->and((int) $niat->amount)->toBe(500000)->and($niat->order_id)->toBe('SIKG-'.$niat->id)->and($niat->gateway_ref)->toBe('TOK-UJI-1')
        ->and($niat->payment_url)->toBe('https://sandbox.doku.com/checkout-link-v2/TOK-UJI-1')->and((int) $niat->active_slot)->toBe(1)->and($niat->created_by)->toBe($s['kasir']->id);
    Http::assertSent(function (Request $r) use ($niat) {
        $isi = $r->body();
        $cocok = tandaTanganDoku(KUNCI_RAHASIA, CLIENT_ID_UJI, $r->header('Request-Id')[0], $r->header('Request-Timestamp')[0], '/checkout/v1/payment', $isi);

        return str_starts_with($r->url(), 'https://api-sandbox.doku.com/checkout/v1/payment') && $r->header('Client-Id')[0] === CLIENT_ID_UJI && $r->header('Signature')[0] === $cocok
            && $r['order']['invoice_number'] === $niat->order_id && $r['order']['amount'] === 500000 && $r['payment']['payment_method_types'] === ['QRIS'] && $r['payment']['payment_due_date'] === 15;
    });

    $this->get("/invoices/{$s['invoice']}/online-payments")->assertOk()->assertInertia(fn ($p) => $p->component('Invoices/OnlinePayment')
        ->where('ready', true)->where('active.amount', 500000)->where('active.status', 'pending')->where('active.payment_url', 'https://sandbox.doku.com/checkout-link-v2/TOK-UJI-1')->where('invoice.balance', 500000));
});

it('P3-05 pembayaran aktif dipakai ulang, jumlah lain menggantinya, dan jumlah tidak sah ditolak', function () {
    $s = siapDaring();
    pasangPenyedia();

    mulaiQr($s)->assertSessionHasNoErrors();
    mulaiQr($s)->assertSessionHasNoErrors();
    expect(DB::table('payment_intents')->count())->toBe(1);

    mulaiQr($s, ['amount' => 200000])->assertSessionHasNoErrors();
    expect(DB::table('payment_intents')->count())->toBe(2)->and(DB::table('payment_intents')->where('status', 'cancelled')->count())->toBe(1)->and(DB::table('payment_intents')->where('status', 'pending')->count())->toBe(1);

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

it('P3-05 notifikasi DOKU yang sah mencatat pembayaran lewat alur biasa dan notifikasi ganda tidak mencatat dua kali', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia();
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();
    $penyedia->status = 'SUCCESS';
    auth()->logout();

    kirimWebhook($s['token'], $niat->order_id)->assertOk()->assertJson(['ok' => true]);
    kirimWebhook($s['token'], $niat->order_id)->assertOk();

    $niatSesudah = niatDaring();
    $bayar = DB::table('payments')->where('invoice_id', $s['invoice'])->get();
    expect($niatSesudah->status)->toBe('paid')->and($niatSesudah->active_slot)->toBeNull()->and($niatSesudah->paid_at)->not->toBeNull()
        ->and($bayar)->toHaveCount(1)->and((int) $bayar[0]->amount)->toBe(500000)->and($bayar[0]->payment_method_id)->toBe($s['qris']->id)->and($bayar[0]->idempotency_key)->toBe('online:'.$niat->id)
        ->and($bayar[0]->reference_no)->toBe('REQ-UJI-1')->and($bayar[0]->received_by)->toBe($s['kasir']->id)->and($niatSesudah->payment_id)->toBe($bayar[0]->id);
    tenant()->set($s['klinik']->id);
    expect(InvoiceModel::query()->findOrFail($s['invoice'])->status)->toBe('paid')
        ->and(DB::table('audit_logs')->where('action', 'online_payment.paid')->count())->toBe(1);
});

it('P3-05 notifikasi ditolak untuk token tak dikenal, tanda tangan salah, Client ID salah, atau isi yang diubah; badan notifikasi bukan sumber kebenaran', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia('PENDING');
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();
    auth()->logout();

    kirimWebhook($s['token'], $niat->order_id, ['alamat' => '/webhooks/payments/'.str_repeat('a', 40)])->assertNotFound();
    kirimWebhook($s['token'], $niat->order_id, ['signature' => 'HMACSHA256=salah'])->assertForbidden();
    kirimWebhook($s['token'], $niat->order_id, ['rahasia' => 'kunci-lain'])->assertForbidden();
    kirimWebhook($s['token'], $niat->order_id, ['client_id' => 'BRN-LAIN'])->assertForbidden();
    // Isi diubah setelah ditandatangani (digest tidak cocok).
    $asli = json_encode(['order' => ['invoice_number' => $niat->order_id]], JSON_THROW_ON_ERROR);
    $ditandatangani = tandaTanganDoku(KUNCI_RAHASIA, CLIENT_ID_UJI, 'req-webhook-1', '2025-06-16T00:00:01Z', "/webhooks/payments/{$s['token']}", $asli);
    kirimWebhook($s['token'], $niat->order_id, ['signature' => $ditandatangani, 'isi' => str_replace($niat->order_id, 'SIKG-LAIN', $asli)])->assertForbidden();

    // Tanpa header sama sekali.
    $this->call('POST', "/webhooks/payments/{$s['token']}", [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertForbidden();

    // Pesanan tak dikenal dijawab OK tanpa efek. Tanda tangan sah tetapi DOKU menjawab masih menunggu: tidak ada pembayaran dicatat.
    kirimWebhook($s['token'], 'SIKG-TIDAK-ADA')->assertOk();
    kirimWebhook($s['token'], $niat->order_id)->assertOk();
    expect(niatDaring()->status)->toBe('pending')->and(DB::table('payments')->count())->toBe(0)->and($penyedia->status)->toBe('PENDING');
});

it('P3-05 token webhook klinik lain tidak dapat menyentuh pembayaran klinik ini', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia('SUCCESS');
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();
    $lain = siapDaring();
    auth()->logout();

    kirimWebhook($lain['token'], $niat->order_id)->assertOk();

    expect(niatDaring()->status)->toBe('pending')->and(DB::table('payments')->count())->toBe(0)->and($penyedia->status)->toBe('SUCCESS');
});

it('P3-05 uang yang tidak dapat dicatat ditandai Mismatch: tagihan sudah lunas tunai, atau jumlah berbeda', function () {
    $s = siapDaring();
    pasangPenyedia('SUCCESS');
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();
    // Pasien membayar tunai di kasir sebelum notifikasi QR tiba.
    $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/payments", ['payment_method_id' => $s['tunai']->id, 'type' => 'pay', 'amount' => 500000], ['Idempotency-Key' => 'k-tunai-1'])->assertSessionHasNoErrors();
    auth()->logout();

    kirimWebhook($s['token'], $niat->order_id)->assertOk();

    expect(niatDaring()->status)->toBe('mismatch')->and(DB::table('payments')->where('invoice_id', $s['invoice'])->count())->toBe(1)->and(niatDaring()->note)->toContain('lunas')
        ->and(DB::table('audit_logs')->where('action', 'online_payment.mismatch')->count())->toBe(1);

    $this->actingAs($s['kasir'])->get('/online-payments')->assertOk()->assertInertia(fn ($p) => $p->component('Invoices/OnlinePayments')->where('intents.0.status', 'mismatch')->where('intents.0.amount', 500000));
});

it('P3-05 jumlah dari DOKU yang berbeda dari tagihan QR tidak dicatat', function () {
    $s = siapDaring();
    pasangPenyedia('SUCCESS', '400000');
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();
    auth()->logout();

    kirimWebhook($s['token'], $niat->order_id)->assertOk();

    expect(niatDaring()->status)->toBe('mismatch')->and(DB::table('payments')->count())->toBe(0);
});

it('P3-05 rekonsiliasi terjadwal mencatat yang sudah dibayar dan menutup yang kedaluwarsa; belum ada percobaan bayar tetap menunggu', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia('PENDING');
    mulaiQr($s, ['amount' => 100000])->assertSessionHasNoErrors();
    $pertama = niatDaring();

    $penyedia->adaPercobaan = false;
    Artisan::call('sikg:payments:reconcile');
    expect(niatDaring()->status)->toBe('pending');
    $penyedia->adaPercobaan = true;

    $penyedia->status = 'SUCCESS';
    $penyedia->jumlah = '100000';
    Artisan::call('sikg:payments:reconcile');
    expect(DB::table('payment_intents')->where('id', $pertama->id)->value('status'))->toBe('paid')->and(DB::table('payments')->count())->toBe(1);

    // QR kedua lewat masa berlaku.
    $penyedia->status = 'PENDING';
    mulaiQr($s, ['amount' => 50000])->assertSessionHasNoErrors();
    Carbon::setTestNow(Carbon::now()->addMinutes(20));
    Artisan::call('sikg:payments:reconcile');
    expect(niatDaring()->status)->toBe('expired')->and((int) DB::table('payment_intents')->where('status', 'pending')->count())->toBe(0);

    // DOKU melaporkan kedaluwarsa.
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:30:00', 'Asia/Jakarta'));
    mulaiQr($s, ['amount' => 50000])->assertSessionHasNoErrors();
    $penyedia->status = 'EXPIRED';
    Artisan::call('sikg:payments:reconcile');
    expect(niatDaring()->status)->toBe('expired');
});

it('P3-05 status FAILED dari DOKU tidak menutup pembayaran karena pasien masih dapat mencoba lagi', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia('FAILED');
    mulaiQr($s)->assertSessionHasNoErrors();

    Artisan::call('sikg:payments:reconcile');
    expect(niatDaring()->status)->toBe('pending');

    $penyedia->status = 'SUCCESS';
    Artisan::call('sikg:payments:reconcile');
    expect(niatDaring()->status)->toBe('paid');
});

it('P3-05 pembatalan tagihan menutup pembayaran yang menunggu, dan pembayaran yang telanjur masuk ditandai Mismatch', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia();
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();

    $this->actingAs($s['admin'])->post("/invoices/{$s['invoice']}/void", ['reason' => 'Salah input'])->assertSessionHasNoErrors();
    expect(niatDaring()->status)->toBe('cancelled')->and(niatDaring()->active_slot)->toBeNull();

    $penyedia->status = 'SUCCESS';
    auth()->logout();
    kirimWebhook($s['token'], $niat->order_id)->assertOk();
    expect(niatDaring()->status)->toBe('mismatch')->and(DB::table('payments')->where('invoice_id', $s['invoice'])->count())->toBe(0);
});

it('P3-05 kasir menutup pembayaran; bila ternyata sudah dibayar, pembayarannya dicatat dan penutupan ditolak', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia();
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();

    $this->actingAs($s['kasir'])->post("/invoices/{$s['invoice']}/online-payments/{$niat->id}/cancel")->assertSessionHasNoErrors();
    expect(niatDaring()->status)->toBe('cancelled');
    $this->post("/invoices/{$s['invoice']}/online-payments/{$niat->id}/cancel")->assertSessionHas('error_code', 'online.not_pending');

    mulaiQr($s)->assertSessionHasNoErrors();
    $kedua = niatDaring();
    $penyedia->status = 'SUCCESS';
    $this->post("/invoices/{$s['invoice']}/online-payments/{$kedua->id}/cancel")->assertSessionHas('error_code', 'online.already_paid_at_gateway');
    expect(niatDaring()->status)->toBe('paid')->and(DB::table('payments')->count())->toBe(1);
});

it('P3-05 galat DOKU membuat pembayaran gagal dengan pesan bersih dari kunci, dan kasir dapat mencoba lagi', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia();
    $penyedia->gagal = true;

    mulaiQr($s)->assertSessionHas('error_code', 'online.gateway_failed');

    expect(niatDaring()->status)->toBe('failed')->and((string) niatDaring()->note)->not->toContain(KUNCI_RAHASIA)->and((int) (niatDaring()->active_slot ?? 0))->toBe(0);
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

it('P3-05 pembayaran dari webhook jatuh ke cabang tagihan dan niat bayar cabang lain tidak dapat dibatalkan dari cabang ini', function () {
    $s = siapDaring();
    $penyedia = pasangPenyedia();
    $cabang = makeBranch($s['klinik'], 'Cabang A');
    mulaiQr($s)->assertSessionHasNoErrors();
    $niat = niatDaring();
    DB::table('invoices')->where('id', $s['invoice'])->update(['branch_id' => $cabang->id]);
    DB::table('invoice_items')->where('invoice_id', $s['invoice'])->update(['branch_id' => $cabang->id]);
    DB::table('payment_intents')->where('id', $niat->id)->update(['branch_id' => $cabang->id]);

    // Kasir terikat cabang lain tidak melihat dan tidak dapat membatalkan niat bayar cabang A.
    $cabangB = makeBranch($s['klinik'], 'Cabang B');
    $kasirB = makeUser(Role::Kasir, $s['klinik'], $cabangB);
    $this->actingAs($kasirB)->get('/online-payments')->assertOk()->assertInertia(fn ($p) => $p->where('intents', []));
    $this->actingAs($kasirB)->post("/invoices/{$s['invoice']}/online-payments/{$niat->id}/cancel")->assertSessionHas('error_code', 'online.not_found');
    expect(niatDaring()->status)->toBe('pending');

    $this->post('/logout');
    $penyedia->status = 'SUCCESS';
    kirimWebhook($s['token'], $niat->order_id)->assertOk();

    expect(DB::table('payments')->where('invoice_id', $s['invoice'])->value('branch_id'))->toBe($cabang->id)
        ->and(niatDaring()->status)->toBe('paid');
});
