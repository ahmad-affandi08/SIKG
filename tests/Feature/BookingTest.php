<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Domain\Role;
use Src\Patient\Infrastructure\Eloquent\PatientModel;
use Src\Scheduling\Contracts\BookingCodeNotSent;
use Src\Scheduling\Contracts\BookingCodeSender;
use Src\Scheduling\Infrastructure\Eloquent\AppointmentModel;
use Src\Shared\Application\PiiProtector;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Klinik uji dengan booking online aktif di /book/klinik-uji (slot 30 menit, dokter praktik Senin 09.00-12.00).
 *
 * @param  array<string, mixed>  $pengaturan
 * @return array<string, mixed>
 */
function siapBooking(array $pengaturan = []): array
{
    test()->withoutMiddleware(ThrottleRequests::class);
    $s = siapJadwal();
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    test()->actingAs($s['admin'])->post('/settings/booking', [
        'enabled' => true, 'slug' => 'klinik-uji', 'slot_minutes' => 30, 'horizon_days' => 30, 'min_lead_hours' => 2, 'hold_hours' => 24, 'max_active_per_phone' => 2,
        'otp_template' => '', 'welcome_text' => 'Selamat datang.', ...$pengaturan,
    ])->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    auth()->logout();

    return $s;
}

/** Pengirim kode palsu yang merekam kode terakhir. */
function pasangPengirimKode(bool $berhasil = true): object
{
    $pengirim = new class($berhasil) implements BookingCodeSender
    {
        /** @var list<array{string, string, string}> */
        public array $terkirim = [];

        public function __construct(private bool $berhasil) {}

        public function available(): bool
        {
            return true;
        }

        public function send(string $phone, string $code, string $template): void
        {
            if (! $this->berhasil) {
                throw new BookingCodeNotSent('Gagal.');
            }
            $this->terkirim[] = [$phone, $code, $template];
        }
    };
    app()->instance(BookingCodeSender::class, $pengirim);

    return $pengirim;
}

/** @param array<string, mixed> $o */
function ajukanBooking(array $s, array $o = [])
{
    return test()->post('/book/klinik-uji', [
        'doctor_id' => $s['dokter']->id, 'date' => '2025-06-16', 'time' => '10:00', 'name' => 'Budi Santoso', 'phone' => '081234567890', 'complaint' => 'Sakit gigi geraham',
        'consent' => true, 'website' => '', ...$o,
    ]);
}

function tokenDariRedirect($response): string
{
    $lokasi = (string) $response->headers->get('Location');
    expect($lokasi)->toMatch('#/book/klinik-uji/r/[a-f0-9]{40}$#');

    return substr($lokasi, -40);
}

function slotBooking(array $s, string $tanggal = '2025-06-16')
{
    return test()->getJson("/internal-api/book/klinik-uji/slots?doctor_id={$s['dokter']->id}&date={$tanggal}");
}

it('P3-01 halaman publik hanya dikenal bila booking aktif dan menampilkan dokter tanpa data lain', function () {
    $s = siapBooking();

    $this->get('/book/klinik-uji')->assertOk()->assertInertia(fn ($p) => $p->component('Booking/Public/Index')->where('slug', 'klinik-uji')->where('doctors.0.name', 'drg. Sari')->where('verification', false)->where('welcome', 'Selamat datang.'));
    $this->get('/book/tidak-ada')->assertNotFound();

    $this->actingAs($s['admin'])->post('/settings/booking', ['enabled' => false, 'slug' => 'klinik-uji', 'slot_minutes' => 30, 'horizon_days' => 30, 'min_lead_hours' => 2, 'hold_hours' => 24, 'max_active_per_phone' => 2])->assertSessionHasNoErrors();
    auth()->logout();
    $this->get('/book/klinik-uji')->assertNotFound();
});

it('P3-01 slot kosong mengikuti jadwal dokter, jeda minimum, dan batas hari', function () {
    $s = siapBooking();

    $slot = slotBooking($s)->assertOk()->json('data');
    // Sekarang 07.00; jeda minimum 2 jam jadi slot paling awal 09.00. Praktik 09.00-12.00 dan 14.00-17.00 (slot terakhir mulai 16.30).
    expect($slot[0])->toBe('09:00')->and(end($slot))->toBe('16:30')->and($slot)->toContain('10:15')->and($slot)->toContain('11:30')->and($slot)->not->toContain('11:45')->and($slot)->not->toContain('13:00');
    expect(slotBooking($s, '2025-06-17')->json('data'))->toContain('09:00')->and(slotBooking($s, '2025-06-18')->json('data'))->toBe([]);

    slotBooking($s, '2025-06-15')->assertStatus(422);
    slotBooking($s, '2025-08-30')->assertStatus(422);
});

it('P3-01 permintaan tersimpan dengan nomor terenkripsi, menahan slot, dan staf diberi tahu tanpa data pasien', function () {
    $s = siapBooking();

    $token = tokenDariRedirect(ajukanBooking($s));

    $baris = DB::table('booking_requests')->first();
    expect($baris->status)->toBe('pending')->and($baris->phone_encrypted)->not->toContain('0812')->and($baris->phone_encrypted)->not->toContain('62812')
        ->and(strlen($baris->phone_hash))->toBe(64)->and($baris->token_hash)->toBe(hash('sha256', $token))->and((int) $baris->verified)->toBe(0)
        ->and($baris->starts_at)->toStartWith('2025-06-16 03:00');
    expect(app(PiiProtector::class)->decrypt($baris->phone_encrypted))->toBe('6281234567890');

    $audit = DB::table('audit_logs')->where('action', 'booking.requested')->first();
    expect($audit)->not->toBeNull()->and(json_encode($audit))->not->toContain('Budi')->and(json_encode($audit))->not->toContain('081234');
    $notif = DB::table('notifications')->where('kind', 'booking.requested')->first();
    expect($notif)->not->toBeNull()->and(json_encode($notif))->not->toContain('Budi');

    // Slot 10.00 sampai 10.30 ditahan (juga yang beririsan), sisanya tetap kosong.
    $slot = slotBooking($s)->json('data');
    expect($slot)->not->toContain('10:00')->and($slot)->not->toContain('09:45')->and($slot)->not->toContain('10:15')->and($slot)->toContain('09:30')->and($slot)->toContain('10:30');

    $this->get("/book/klinik-uji/r/{$token}")->assertOk()->assertInertia(fn ($p) => $p->component('Booking/Public/Status')
        ->where('booking.status', 'pending')->where('booking.first_name', 'Budi')->where('booking.time', '10:00')->where('booking.can_cancel', true));
});

it('P3-01 menolak slot yang sudah terisi, nomor tidak sah, tanpa persetujuan, dan isian jebakan bot', function () {
    $s = siapBooking();
    tokenDariRedirect(ajukanBooking($s));

    ajukanBooking($s, ['name' => 'Siti Aminah', 'phone' => '081298765432'])->assertSessionHas('error_code', 'booking.slot_unavailable');
    ajukanBooking($s, ['time' => '11:00', 'phone' => '12345'])->assertSessionHas('error_code', 'booking.phone_invalid');
    ajukanBooking($s, ['time' => '11:00', 'consent' => false])->assertSessionHasErrors('consent');
    ajukanBooking($s, ['time' => '11:00', 'website' => 'http://spam.example'])->assertSessionHasErrors('website');
    ajukanBooking($s, ['time' => '11:00', 'name' => 'Bu'])->assertSessionHasErrors('name');
    ajukanBooking($s, ['time' => '08:00'])->assertSessionHas('error_code', 'booking.slot_unavailable');
    ajukanBooking($s, ['date' => '2025-07-30', 'time' => '09:00'])->assertSessionHas('error_code', 'booking.date_out_of_range');
    expect(DB::table('booking_requests')->count())->toBe(1);
});

it('P3-01 membatasi janji aktif per nomor HP, termasuk format nomor yang berbeda', function () {
    $s = siapBooking();

    tokenDariRedirect(ajukanBooking($s, ['time' => '09:00']));
    tokenDariRedirect(ajukanBooking($s, ['time' => '10:00', 'phone' => '+62 812-3456-7890']));
    ajukanBooking($s, ['time' => '11:00', 'phone' => '6281234567890'])->assertSessionHas('error_code', 'booking.phone_limit');
    // Nomor lain tidak terpengaruh.
    tokenDariRedirect(ajukanBooking($s, ['time' => '11:00', 'phone' => '081311112222']));
    expect(DB::table('booking_requests')->count())->toBe(3);
});

it('P3-01 pasien membatalkan sendiri dan slot terbuka lagi serta batas nomor pulih', function () {
    $s = siapBooking(['max_active_per_phone' => 1]);
    $token = tokenDariRedirect(ajukanBooking($s));
    ajukanBooking($s, ['time' => '11:00'])->assertSessionHas('error_code', 'booking.phone_limit');

    $this->post("/book/klinik-uji/r/{$token}/cancel")->assertSessionHasNoErrors();

    expect(DB::table('booking_requests')->value('status'))->toBe('cancelled')->and(slotBooking($s)->json('data'))->toContain('10:00');
    $this->post("/book/klinik-uji/r/{$token}/cancel")->assertSessionHas('error_code', 'booking.cannot_cancel');
    tokenDariRedirect(ajukanBooking($s, ['time' => '11:00']));
});

it('P3-01 token status yang salah atau milik klinik lain memberi 404', function () {
    $s = siapBooking();
    $token = tokenDariRedirect(ajukanBooking($s));

    $this->get('/book/klinik-uji/r/'.str_repeat('a', 40))->assertNotFound();
    $this->get('/book/klinik-uji/r/pendek')->assertNotFound();

    $lain = siapJadwal();
    $adminLain = makeUser(Role::Admin, $lain['klinik']);
    $this->actingAs($adminLain)->post('/settings/booking', ['enabled' => true, 'slug' => 'klinik-lain', 'slot_minutes' => 30, 'horizon_days' => 30, 'min_lead_hours' => 2, 'hold_hours' => 24, 'max_active_per_phone' => 2])->assertSessionHasNoErrors();
    auth()->logout();
    $this->get("/book/klinik-lain/r/{$token}")->assertNotFound();
});

it('P3-01 kode verifikasi WhatsApp: salah ditolak, benar mengubah status, dan batasnya dijaga', function () {
    $s = siapBooking(['otp_template' => 'kode_booking']);
    $pengirim = pasangPengirimKode();

    $token = tokenDariRedirect(ajukanBooking($s));

    expect(DB::table('booking_requests')->value('status'))->toBe('awaiting_code')->and($pengirim->terkirim)->toHaveCount(1)->and($pengirim->terkirim[0][0])->toBe('6281234567890')->and($pengirim->terkirim[0][2])->toBe('kode_booking');
    $kode = $pengirim->terkirim[0][1];
    expect($kode)->toMatch('/^\d{6}$/')->and(DB::table('booking_requests')->value('code_hash'))->not->toContain($kode);
    $this->get('/book/klinik-uji')->assertInertia(fn ($p) => $p->where('verification', true));
    expect(DB::table('notifications')->where('kind', 'booking.requested')->count())->toBe(0);

    $salah = $kode === '123456' ? '654321' : '123456';
    $this->post("/book/klinik-uji/r/{$token}/verify", ['code' => $salah])->assertSessionHas('error_code', 'booking.code_wrong');
    expect((int) DB::table('booking_requests')->value('code_attempts'))->toBe(1);
    $this->post("/book/klinik-uji/r/{$token}/verify", ['code' => 'abc'])->assertSessionHasErrors('code');

    $this->post("/book/klinik-uji/r/{$token}/verify", ['code' => $kode])->assertSessionHasNoErrors();
    $baris = DB::table('booking_requests')->first();
    expect($baris->status)->toBe('pending')->and((int) $baris->verified)->toBe(1)->and($baris->code_hash)->toBeNull()
        ->and(DB::table('notifications')->where('kind', 'booking.requested')->count())->toBe(1);
});

it('P3-01 kode kedaluwarsa, percobaan berlebih, dan kirim ulang dibatasi', function () {
    $s = siapBooking(['otp_template' => 'kode_booking']);
    $pengirim = pasangPengirimKode();
    $token = tokenDariRedirect(ajukanBooking($s));

    foreach (range(1, 5) as $i) {
        $this->post("/book/klinik-uji/r/{$token}/verify", ['code' => '000000'])->assertSessionHas('error_code', 'booking.code_wrong');
    }
    $this->post("/book/klinik-uji/r/{$token}/verify", ['code' => $pengirim->terkirim[0][1]])->assertSessionHas('error_code', 'booking.code_attempts');

    $this->post("/book/klinik-uji/r/{$token}/resend")->assertSessionHasNoErrors();
    expect($pengirim->terkirim)->toHaveCount(2);
    $this->post("/book/klinik-uji/r/{$token}/verify", ['code' => $pengirim->terkirim[1][1]])->assertSessionHasNoErrors();
    expect(DB::table('booking_requests')->value('status'))->toBe('pending');

    // Kode yang lewat 10 menit ditolak.
    $kedua = tokenDariRedirect(ajukanBooking($s, ['time' => '11:00']));
    Carbon::setTestNow(Carbon::now()->addMinutes(11));
    $this->post("/book/klinik-uji/r/{$kedua}/verify", ['code' => $pengirim->terkirim[2][1]])->assertSessionHas('error_code', 'booking.code_expired');

    // Kirim ulang paling banyak 3 kode per permintaan.
    $this->post("/book/klinik-uji/r/{$kedua}/resend")->assertSessionHasNoErrors();
    $this->post("/book/klinik-uji/r/{$kedua}/resend")->assertSessionHasNoErrors();
    $this->post("/book/klinik-uji/r/{$kedua}/resend")->assertSessionHas('error_code', 'booking.code_limit');
});

it('P3-01 kode yang gagal dikirim membatalkan permintaan agar slot tidak tertahan', function () {
    $s = siapBooking(['otp_template' => 'kode_booking']);
    pasangPengirimKode(false);

    ajukanBooking($s)->assertSessionHas('error_code', 'booking.code_failed');

    expect(DB::table('booking_requests')->value('status'))->toBe('cancelled')->and(slotBooking($s)->json('data'))->toContain('10:00');
});

it('P3-01 staf mengonfirmasi permintaan menjadi janji untuk pasien terdaftar dan slot terisi janji', function () {
    $s = siapBooking();
    $token = tokenDariRedirect(ajukanBooking($s));
    $id = (string) DB::table('booking_requests')->value('id');

    $this->actingAs($s['user'])->get('/booking-requests')->assertOk()->assertInertia(fn ($p) => $p->component('Booking/Requests/Index')
        ->where('public_path', '/book/klinik-uji')->where('requests.0.patient_name', 'Budi Santoso')->where('requests.0.phone_masked', '*********7890')->where('requests.0.doctor', 'drg. Sari')->where('requests.0.verified', false));

    // Nomor belum terverifikasi: staf wajib menghubungi pasien lebih dulu.
    $this->post("/booking-requests/{$id}/confirm", ['patient_id' => $s['pasien']])->assertSessionHas('error_code', 'booking.not_verified');
    $this->post("/booking-requests/{$id}/confirm", ['patient_id' => str_repeat('0', 26), 'contacted' => true])->assertSessionHas('error_code', 'booking.patient_not_found');

    $this->post("/booking-requests/{$id}/confirm", ['patient_id' => $s['pasien'], 'contacted' => true])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $janji = AppointmentModel::query()->firstOrFail();
    $baris = DB::table('booking_requests')->first();
    expect($baris->status)->toBe('confirmed')->and($baris->appointment_id)->toBe($janji->id)->and($baris->patient_id)->toBe($s['pasien'])->and($baris->decided_by)->toBe($s['user']->id)
        ->and($janji->source)->toBe('booking')->and($janji->complaint)->toBe('Sakit gigi geraham')->and($janji->starts_at->format('Y-m-d H:i'))->toBe('2025-06-16 03:00')
        ->and($janji->ends_at->format('H:i'))->toBe('03:30')->and($janji->patient_id)->toBe($s['pasien']);
    expect(DB::table('audit_logs')->where('action', 'booking.confirmed')->count())->toBe(1);

    // Slot tetap tidak tersedia (kini oleh janji), dan sudah tidak dapat dikonfirmasi lagi.
    expect(slotBooking($s)->json('data'))->not->toContain('10:00');
    $this->post("/booking-requests/{$id}/confirm", ['patient_id' => $s['pasien'], 'contacted' => true])->assertSessionHas('error_code', 'booking.not_pending');
    auth()->logout();
    $this->get("/book/klinik-uji/r/{$token}")->assertInertia(fn ($p) => $p->where('booking.status', 'confirmed')->where('booking.can_cancel', false));
});

it('P3-01 nomor yang diverifikasi dapat dikonfirmasi tanpa menghubungi pasien dan kandidat pasien dicari lewat nomor', function () {
    $s = siapBooking(['otp_template' => 'kode_booking']);
    $pengirim = pasangPengirimKode();
    $token = tokenDariRedirect(ajukanBooking($s));
    $this->post("/book/klinik-uji/r/{$token}/verify", ['code' => $pengirim->terkirim[0][1]])->assertSessionHasNoErrors();

    // Pasien terdaftar memakai nomor yang sama (blind index sama dengan milik pasien).
    tenant()->set($s['klinik']->id);
    $pii = app(PiiProtector::class);
    PatientModel::query()->whereKey($s['pasien'])->update(['phone_encrypted' => $pii->encrypt('6281234567890'), 'phone_hash' => $pii->blindIndex('phone', '6281234567890')]);

    $id = (string) DB::table('booking_requests')->value('id');
    $this->actingAs($s['user'])->get('/booking-requests')->assertInertia(fn ($p) => $p->where('requests.0.verified', true)->where('requests.0.matches.0.id', $s['pasien'])->where('requests.0.matches.0.name', 'Budi Santoso'));
    $this->post("/booking-requests/{$id}/confirm", ['patient_id' => $s['pasien']])->assertSessionHasNoErrors();

    expect(DB::table('booking_requests')->value('status'))->toBe('confirmed');
});

it('P3-01 konfirmasi gagal bila slot sudah diambil janji lain dan permintaan tetap menunggu', function () {
    $s = siapBooking();
    tokenDariRedirect(ajukanBooking($s));
    $id = (string) DB::table('booking_requests')->value('id');
    // Resepsionis membuat janji langsung pada jam yang sama dengan dokter yang sama.
    bukaJanji($s, ['time' => '10:00', 'chair_id' => null]);

    $this->post("/booking-requests/{$id}/confirm", ['patient_id' => $s['pasien'], 'contacted' => true])->assertSessionHas('error_code', 'appointment.conflict');

    expect(DB::table('booking_requests')->value('status'))->toBe('pending');
});

it('P3-01 staf menolak dengan alasan dan pasien melihat alasannya', function () {
    $s = siapBooking();
    $token = tokenDariRedirect(ajukanBooking($s));
    $id = (string) DB::table('booking_requests')->value('id');

    $this->actingAs($s['user'])->post("/booking-requests/{$id}/reject", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post("/booking-requests/{$id}/reject", ['reason' => 'Dokter berhalangan hari itu'])->assertSessionHasNoErrors();
    $this->post("/booking-requests/{$id}/reject", ['reason' => 'Lagi'])->assertSessionHas('error_code', 'booking.not_pending');

    expect(DB::table('booking_requests')->value('status'))->toBe('rejected')->and(slotBooking($s)->json('data'))->toContain('10:00');
    auth()->logout();
    $this->get("/book/klinik-uji/r/{$token}")->assertInertia(fn ($p) => $p->where('booking.status', 'rejected')->where('booking.note', 'Dokter berhalangan hari itu'));
});

it('P3-01 permintaan melewati masa tahan menjadi kedaluwarsa lewat perintah terjadwal', function () {
    $s = siapBooking(['hold_hours' => 2]);
    tokenDariRedirect(ajukanBooking($s));
    tokenDariRedirect(ajukanBooking($s, ['time' => '11:00', 'phone' => '081311112222']));

    Carbon::setTestNow(Carbon::now()->addHours(3));
    Artisan::call('sikg:booking:expire');

    expect(DB::table('booking_requests')->where('status', 'expired')->count())->toBe(2);
    Artisan::call('sikg:booking:expire');
    expect(DB::table('booking_requests')->where('status', 'expired')->count())->toBe(2);
});

it('P3-01 nomor HP penuh hanya dibuka petugas berwenang dan pembukaannya diaudit', function () {
    $s = siapBooking();
    tokenDariRedirect(ajukanBooking($s));
    $id = (string) DB::table('booking_requests')->value('id');
    $dokter = makeUser(Role::Dokter, $s['klinik']);

    $this->actingAs($dokter)->postJson("/internal-api/booking-requests/{$id}/phone")->assertForbidden();
    $this->actingAs($s['user'])->postJson("/internal-api/booking-requests/{$id}/phone")->assertOk()->assertJsonPath('data.phone', '6281234567890');

    expect(DB::table('audit_logs')->where('action', 'booking.phone_viewed')->where('subject_id', $id)->count())->toBe(1);
});

it('P3-01 hak akses: tamu diarahkan masuk, dokter tidak mengelola, pengaturan hanya Admin, dan klinik lain tidak melihat', function () {
    $s = siapBooking();
    tokenDariRedirect(ajukanBooking($s));
    $id = (string) DB::table('booking_requests')->value('id');

    $this->get('/booking-requests')->assertRedirect('/login');
    $this->get('/settings/booking')->assertRedirect('/login');
    $dokter = makeUser(Role::Dokter, $s['klinik']);
    $this->actingAs($dokter)->get('/booking-requests')->assertForbidden();
    $this->actingAs($s['user'])->get('/settings/booking')->assertForbidden();
    $this->actingAs($s['user'])->post('/settings/booking', [])->assertForbidden();
    $this->actingAs($s['admin'])->get('/settings/booking')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/Booking')->where('settings.slug', 'klinik-uji'));

    $lain = siapJadwal();
    $this->actingAs($lain['user'])->get('/booking-requests')->assertOk()->assertInertia(fn ($p) => $p->where('requests', []));
    $this->post("/booking-requests/{$id}/confirm", ['patient_id' => $lain['pasien'], 'contacted' => true])->assertNotFound();
    $this->post("/booking-requests/{$id}/reject", ['reason' => 'x'])->assertNotFound();
    expect(DB::table('booking_requests')->value('status'))->toBe('pending');
});

it('P3-01 pengaturan: alamat divalidasi, unik antarklinik, dan perubahan diaudit', function () {
    $s = siapBooking();
    $dasar = ['enabled' => true, 'slot_minutes' => 30, 'horizon_days' => 30, 'min_lead_hours' => 2, 'hold_hours' => 24, 'max_active_per_phone' => 2];
    $this->actingAs($s['admin']);

    $this->post('/settings/booking', [...$dasar, 'slug' => 'Klinik Uji'])->assertSessionHas('error_code', 'booking.slug_invalid');
    $this->post('/settings/booking', [...$dasar, 'slug' => ''])->assertSessionHas('error_code', 'booking.slug_required');
    $this->post('/settings/booking', [...$dasar, 'slug' => 'klinik-uji', 'slot_minutes' => 17])->assertSessionHas('error_code', 'booking.settings_invalid');
    $this->post('/settings/booking', [...$dasar, 'slug' => 'klinik-uji', 'max_active_per_phone' => 0])->assertSessionHasErrors('max_active_per_phone');

    $lain = siapJadwal();
    $adminLain = makeUser(Role::Admin, $lain['klinik']);
    $this->actingAs($adminLain)->post('/settings/booking', [...$dasar, 'slug' => 'klinik-uji'])->assertSessionHas('error_code', 'booking.slug_taken');

    $this->actingAs($s['admin'])->post('/settings/booking', [...$dasar, 'slug' => 'klinik-uji', 'slot_minutes' => 45])->assertSessionHasNoErrors();
    expect(DB::table('audit_logs')->where('action', 'booking.settings_changed')->count())->toBe(2);
});

it('P3-01 penggabungan pasien memindahkan permintaan booking yang sudah dikaitkan', function () {
    $s = siapBooking();
    tokenDariRedirect(ajukanBooking($s));
    $id = (string) DB::table('booking_requests')->value('id');
    $this->actingAs($s['user'])->post("/booking-requests/{$id}/confirm", ['patient_id' => $s['pasien'], 'contacted' => true])->assertSessionHasNoErrors();
    $utama = makePatient($s['klinik'], 'Pasien Utama');
    tenant()->set($s['klinik']->id);

    $label = [];
    foreach (app()->tagged('sikg.patient-movers') as $mover) {
        $label[$mover->label()] = $mover;
    }
    expect($label)->toHaveKey('Permintaan booking online')->and($label['Permintaan booking online']->count($s['pasien']))->toBe(1);
    $label['Permintaan booking online']->move($s['pasien'], $utama);

    expect(DB::table('booking_requests')->value('patient_id'))->toBe($utama);
});
