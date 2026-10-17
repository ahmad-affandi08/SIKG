<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Application\PiiProtector;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function nomorPasien(string $pasien, string $nomor = '0812-3456-789'): void
{
    DB::table('patients')->where('id', $pasien)->update(['phone_encrypted' => app(PiiProtector::class)->encrypt($nomor)]);
}

/**
 * Jadwal dengan janji Selasa 09.00 untuk pasien bernomor HP, pengingat aktif, dan peran yang dibutuhkan.
 *
 * @return array<string, mixed>
 */
function siapPengingat(bool $aktif = true, bool $janji = true): array
{
    $s = siapJadwal();
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    $s['perawat'] = makeUser(Role::Perawat, $s['klinik']);
    nomorPasien($s['pasien']);
    if ($janji) {
        $s['janji'] = bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);
    }
    if ($aktif) {
        aturPengingat($s);
    }

    return $s;
}

/** @param array<string, mixed> $o */
function pengaturanPengingat(array $o = []): array
{
    return [
        'enabled' => true, 'mode' => 'link', 'recall_months' => 6, 'api_phone_number_id' => '', 'api_token' => '', 'api_language' => 'id',
        'kinds' => ['h1' => ['enabled' => true, 'template' => '', 'api_template' => ''], 'h0' => ['enabled' => true, 'template' => '', 'api_template' => ''], 'recall' => ['enabled' => true, 'template' => '', 'api_template' => '']],
        ...$o,
    ];
}

/** @param array<string, mixed> $o */
function aturPengingat(array $s, array $o = [])
{
    return test()->actingAs($s['admin'])->put('/settings/reminders', pengaturanPengingat($o))->assertSessionHasNoErrors();
}

function jalankanPengingat(): void
{
    test()->artisan('sikg:reminders:run')->assertSuccessful();
}

function pengingatDb(): Collection
{
    return DB::table('patient_reminders')->orderBy('created_at')->orderBy('id')->get();
}

it('P2-03 pengaturan pengingat disimpan, token terenkripsi dan tidak ditampilkan', function () {
    $s = siapPengingat(aktif: false, janji: false);

    aturPengingat($s, ['mode' => 'api', 'api_phone_number_id' => '109876', 'api_token' => 'token-rahasia-123', 'kinds' => ['h1' => ['enabled' => true, 'template' => 'Halo {nama}, besok {jam}', 'api_template' => 'pengingat_h1'], 'h0' => ['enabled' => false, 'template' => '', 'api_template' => ''], 'recall' => ['enabled' => true, 'template' => '', 'api_template' => '']]]);

    $baris = DB::table('reminder_settings')->first();
    expect($baris->api_token_encrypted)->not->toContain('token-rahasia-123')->and($baris->mode)->toBe('api')->and((bool) $baris->h0_enabled)->toBeFalse()->and($baris->h1_template)->toBe('Halo {nama}, besok {jam}');
    $halaman = $this->get('/settings/reminders')->assertOk();
    $halaman->assertInertia(fn ($p) => $p->component('Settings/Reminders')->where('settings.has_token', true)->where('settings.api_ready', true)->missing('settings.api_token'));
    expect($halaman->getContent())->not->toContain('token-rahasia-123');
    expect(json_encode(DB::table('audit_logs')->where('action', 'reminder.settings_updated')->get()))->not->toContain('token-rahasia-123');

    aturPengingat($s, ['mode' => 'api', 'api_phone_number_id' => '109876', 'api_token' => '']);
    expect(DB::table('reminder_settings')->value('api_token_encrypted'))->toBe($baris->api_token_encrypted);
});

it('P2-03 pengaturan menolak penanda tidak dikenal, mode API tanpa isian, dan jarak di luar batas', function () {
    $s = siapPengingat(aktif: false, janji: false);
    $this->actingAs($s['admin']);

    $this->put('/settings/reminders', pengaturanPengingat(['kinds' => ['h1' => ['enabled' => true, 'template' => 'Halo {namma}', 'api_template' => ''], 'h0' => ['enabled' => true, 'template' => '', 'api_template' => ''], 'recall' => ['enabled' => true, 'template' => '', 'api_template' => '']]]))->assertSessionHas('error_code', 'reminder.invalid');
    $this->put('/settings/reminders', pengaturanPengingat(['mode' => 'api']))->assertSessionHas('error_code', 'reminder.invalid');
    $this->put('/settings/reminders', pengaturanPengingat(['recall_months' => 30]))->assertSessionHasErrors('recall_months');
    $this->put('/settings/reminders', pengaturanPengingat(['kinds' => ['h1' => ['enabled' => true, 'template' => '', 'api_template' => 'Nama Salah'], 'h0' => ['enabled' => true, 'template' => '', 'api_template' => ''], 'recall' => ['enabled' => true, 'template' => '', 'api_template' => '']]]))->assertSessionHasErrors('kinds.h1.api_template');
    expect(DB::table('reminder_settings')->count())->toBe(0);
});

it('P2-03 janji besok masuk pengingat H-1 setelah pukul 09.00 dan hanya sekali', function () {
    $s = siapPengingat();

    jalankanPengingat();
    $baris = pengingatDb();
    expect($baris)->toHaveCount(1)->and($baris[0]->kind)->toBe('h1')->and($baris[0]->status)->toBe('pending')->and($baris[0]->appointment_id)->toBe($s['janji']);

    jalankanPengingat();
    expect(pengingatDb())->toHaveCount(1);
});

it('P2-03 pengingat H-1 menunggu pukul 09.00 dan pengingat hari-H mulai 07.00 sebelum janji', function () {
    $s = siapPengingat();

    Carbon::setTestNow(Carbon::parse('2025-06-16 08:59:00', 'Asia/Jakarta'));
    jalankanPengingat();
    expect(pengingatDb())->toHaveCount(0);

    Carbon::setTestNow(Carbon::parse('2025-06-17 06:59:00', 'Asia/Jakarta'));
    jalankanPengingat();
    expect(pengingatDb())->toHaveCount(0);

    Carbon::setTestNow(Carbon::parse('2025-06-17 07:00:00', 'Asia/Jakarta'));
    jalankanPengingat();
    expect(pengingatDb()->pluck('kind')->all())->toBe(['h0']);

    Carbon::setTestNow(Carbon::parse('2025-06-17 09:30:00', 'Asia/Jakarta'));
    $this->flushSession(); // lompatan jam melewati batas tidak aktif sesi
    $lain = bukaJanji($s, ['date' => SELASA, 'time' => '11:00', 'patient_id' => makePatient($s['klinik'], 'Pasien Lain')]);
    jalankanPengingat();
    expect(pengingatDb())->toHaveCount(2);
});

it('P2-03 jenis yang dimatikan, janji batal, pasien yang menolak, atau pengingat mati tidak membuat pengingat', function () {
    $s = siapPengingat(aktif: false);
    jalankanPengingat();
    expect(pengingatDb())->toHaveCount(0);

    aturPengingat($s, ['kinds' => ['h1' => ['enabled' => false, 'template' => '', 'api_template' => ''], 'h0' => ['enabled' => true, 'template' => '', 'api_template' => ''], 'recall' => ['enabled' => true, 'template' => '', 'api_template' => '']]]);
    jalankanPengingat();
    expect(pengingatDb())->toHaveCount(0);

    aturPengingat($s);
    DB::table('appointments')->where('id', $s['janji'])->update(['status' => 'cancelled']);
    jalankanPengingat();
    expect(pengingatDb())->toHaveCount(0);

    DB::table('appointments')->where('id', $s['janji'])->update(['status' => 'scheduled']);
    DB::table('reminder_optouts')->insert(['id' => strtoupper((string) Str::ulid()), 'clinic_id' => $s['klinik']->id, 'patient_id' => $s['pasien'], 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    jalankanPengingat();
    expect(pengingatDb())->toHaveCount(0);
});

it('P2-03 kontrol berkala dibuat untuk kunjungan terakhir yang ditutup sekitar enam bulan lalu', function () {
    $s = siapKunjungan();
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    nomorPasien($s['pasien']);
    aturPengingat($s);
    $this->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()]);
    $this->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();

    Carbon::setTestNow(Carbon::parse('2025-12-01 10:00:00', 'Asia/Jakarta'));
    jalankanPengingat();
    expect(pengingatDb())->toHaveCount(0);

    Carbon::setTestNow(Carbon::parse('2025-12-20 10:00:00', 'Asia/Jakarta'));
    jalankanPengingat();
    $baris = pengingatDb();
    expect($baris)->toHaveCount(1)->and($baris[0]->kind)->toBe('recall')->and($baris[0]->encounter_id)->toBe($s['kunjungan']);

    jalankanPengingat();
    expect(pengingatDb())->toHaveCount(1);
});

it('P2-03 kontrol berkala dilewati bila pasien sudah punya janji berikutnya atau sudah ada kunjungan yang lebih baru', function () {
    $s = siapKunjungan();
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    aturPengingat($s);
    $this->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()]);
    $this->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $janji = bukaJanji($s, ['date' => '2025-12-23', 'time' => '10:00']);

    Carbon::setTestNow(Carbon::parse('2025-12-20 10:00:00', 'Asia/Jakarta'));
    jalankanPengingat();
    expect(pengingatDb()->where('kind', 'recall'))->toHaveCount(0);

    DB::table('appointments')->where('id', $janji)->update(['status' => 'cancelled']);
    DB::table('encounters')->insert([
        'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $s['klinik']->id, 'patient_id' => $s['pasien'], 'doctor_id' => $s['dokter']->id, 'started_at' => '2025-11-01 02:00:00',
        'closed_at' => '2025-11-01 03:00:00', 'status' => 'closed', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    jalankanPengingat();
    expect(pengingatDb()->where('kind', 'recall'))->toHaveCount(0);
});

it('P2-03 meja pengingat menampilkan pesan tanpa nomor HP dan hanya untuk pengelola jadwal', function () {
    $s = siapPengingat();
    jalankanPengingat();

    $halaman = $this->actingAs($s['user'])->get('/reminders')->assertOk();
    $halaman->assertInertia(fn ($p) => $p->component('Reminders/Index')->has('pending', 1)->where('pending.0.kind', 'h1')->where('pending.0.has_phone', true)
        ->where('pending.0.message', fn ($pesan) => str_contains($pesan, 'Yth. Budi') && str_contains($pesan, 'besok, 17/06/2025 pukul 09:00') && str_contains($pesan, 'drg. Sari') && str_contains($pesan, 'Klinik Uji')));
    expect($halaman->getContent())->not->toContain('628123456789')->and($halaman->getContent())->not->toContain('0812');

    $this->actingAs($s['perawat'])->get('/reminders')->assertForbidden();
    $this->post('/internal-api/reminders/'.pengingatDb()[0]->id.'/link')->assertForbidden();
    $this->actingAs(makeUser(Role::Dokter, $s['klinik']))->get('/reminders')->assertForbidden();
});

it('P2-03 tautan WhatsApp dibuat di server dengan nomor internasional dan akses tercatat', function () {
    $s = siapPengingat();
    jalankanPengingat();
    $id = pengingatDb()[0]->id;

    $url = $this->actingAs($s['user'])->postJson("/internal-api/reminders/{$id}/link")->assertOk()->json('data.url');

    expect($url)->toStartWith('https://wa.me/628123456789?text=')->and(urldecode($url))->toContain('Yth. Budi')->toContain('drg. Sari');
    expect(DB::table('access_logs')->where('patient_id', $s['pasien'])->where('route', 'reminders.link')->exists())->toBeTrue();
    $this->postJson('/internal-api/reminders/'.strtoupper((string) Str::ulid()).'/link')->assertStatus(422)->assertJsonPath('code', 'reminder.invalid');
});

it('P2-03 pasien tanpa nomor HP tidak mendapat tautan dan janji yang batal melewati pengingat', function () {
    $s = siapPengingat();
    jalankanPengingat();
    $id = pengingatDb()[0]->id;

    DB::table('patients')->where('id', $s['pasien'])->update(['phone_encrypted' => null]);
    $this->actingAs($s['user'])->postJson("/internal-api/reminders/{$id}/link")->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'nomor HP'));
    $this->get('/reminders')->assertInertia(fn ($p) => $p->where('pending.0.has_phone', false));

    nomorPasien($s['pasien']);
    DB::table('appointments')->where('id', $s['janji'])->update(['status' => 'cancelled']);
    $this->postJson("/internal-api/reminders/{$id}/link")->assertStatus(422);
    expect(DB::table('patient_reminders')->where('id', $id)->value('status'))->toBe('skipped');
});

it('P2-03 petugas menandai terkirim, melewati, dan mencatat pasien yang tidak mau diingatkan', function () {
    $s = siapPengingat();
    $kedua = makePatient($s['klinik'], 'Citra Lestari');
    nomorPasien($kedua, '081300000001');
    bukaJanji($s, ['date' => SELASA, 'time' => '10:00', 'patient_id' => $kedua, 'doctor_id' => $s['dokter']->id, 'chair_id' => null]);
    jalankanPengingat();
    [$a, $b] = pengingatDb()->pluck('id')->all();

    $this->actingAs($s['user'])->post("/reminders/{$a}/sent")->assertSessionHasNoErrors();
    $baris = DB::table('patient_reminders')->where('id', $a)->first();
    expect($baris->status)->toBe('sent')->and($baris->channel)->toBe('link')->and($baris->sent_by)->toBe($s['user']->id)->and($baris->sent_at)->not->toBeNull();
    $this->post("/reminders/{$a}/sent")->assertSessionHas('error_code', 'reminder.invalid');

    $this->post("/reminders/{$b}/opt-out")->assertSessionHasNoErrors();
    expect(DB::table('patient_reminders')->where('id', $b)->value('status'))->toBe('skipped')->and(DB::table('reminder_optouts')->where('is_active', true)->count())->toBe(1);
    $this->get('/reminders')->assertInertia(fn ($p) => $p->has('optouts', 1)->has('history', 2));

    $this->post('/reminders/patients/'.DB::table('reminder_optouts')->value('patient_id').'/opt-in')->assertSessionHasNoErrors();
    expect(DB::table('reminder_optouts')->where('is_active', true)->count())->toBe(0);
    expect(DB::table('audit_logs')->whereIn('action', ['reminder.marked_sent', 'reminder.opted_out', 'reminder.opted_in'])->count())->toBe(3);
});

it('P2-03 mode API mengirim pesan templat berurutan parameter dan mencatat terkirim', function () {
    $s = siapPengingat(aktif: false);
    aturPengingat($s, ['mode' => 'api', 'api_phone_number_id' => '109876', 'api_token' => 'token-wa', 'kinds' => ['h1' => ['enabled' => true, 'template' => '', 'api_template' => 'pengingat_h1'], 'h0' => ['enabled' => true, 'template' => '', 'api_template' => 'pengingat_h0'], 'recall' => ['enabled' => true, 'template' => '', 'api_template' => 'kontrol']]]);
    Http::swap(new Factory);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

    jalankanPengingat();

    $baris = pengingatDb()[0];
    expect($baris->status)->toBe('sent')->and($baris->channel)->toBe('api')->and($baris->attempts)->toBe(1);
    Http::assertSent(function (Request $r) {
        $data = $r->data();

        return str_contains($r->url(), '/109876/messages') && $r->hasHeader('Authorization', 'Bearer token-wa') && $data['to'] === '628123456789'
            && $data['template']['name'] === 'pengingat_h1' && $data['template']['language']['code'] === 'id'
            && array_column($data['template']['components'][0]['parameters'], 'text') === ['Budi', '17/06/2025', '09:00', 'drg. Sari', 'Klinik Uji'];
    });
    Http::assertSentCount(1);
});

it('P2-03 mode API mencoba ulang gangguan sementara, gagal setelah tiga percobaan, dan menolak permanen langsung', function () {
    $s = siapPengingat(aktif: false);
    aturPengingat($s, ['mode' => 'api', 'api_phone_number_id' => '109876', 'api_token' => 'token-wa', 'kinds' => ['h1' => ['enabled' => true, 'template' => '', 'api_template' => 'pengingat_h1'], 'h0' => ['enabled' => true, 'template' => '', 'api_template' => ''], 'recall' => ['enabled' => true, 'template' => '', 'api_template' => '']]]);
    Http::swap(new Factory);
    Http::fake(['graph.facebook.com/*' => Http::response('', 503)]);

    jalankanPengingat();
    expect(pengingatDb()[0]->status)->toBe('pending')->and(pengingatDb()[0]->attempts)->toBe(1)->and(pengingatDb()[0]->last_error)->toContain('503');
    jalankanPengingat();
    jalankanPengingat();
    expect(pengingatDb()[0]->status)->toBe('failed')->and(pengingatDb()[0]->attempts)->toBe(3);

    $lain = makePatient($s['klinik'], 'Citra Lestari');
    nomorPasien($lain, '081300000002');
    bukaJanji($s, ['date' => SELASA, 'time' => '10:00', 'patient_id' => $lain, 'chair_id' => null]);
    Http::swap(new Factory);
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 131030, 'message' => 'Recipient Budi 6281 not in allowed list']], 400)]);
    jalankanPengingat();
    $baru = pengingatDb()->last();
    expect($baru->status)->toBe('failed')->and($baru->attempts)->toBe(1)->and($baru->last_error)->toContain('HTTP 400')->toContain('131030')->not->toContain('Budi')->not->toContain('6281');
});

it('P2-03 mode API melewati pengingat tanpa nomor HP, tanpa nama templat, atau janji yang sudah batal', function () {
    $s = siapPengingat(aktif: false);
    aturPengingat($s, ['mode' => 'api', 'api_phone_number_id' => '109876', 'api_token' => 'token-wa']);
    Http::swap(new Factory);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => []])]);

    jalankanPengingat(); // nama templat WhatsApp belum diisi
    expect(pengingatDb()[0]->status)->toBe('skipped')->and(pengingatDb()[0]->last_error)->toContain('templat');
    Http::assertNothingSent();

    DB::table('patient_reminders')->delete();
    DB::table('patients')->where('id', $s['pasien'])->update(['phone_encrypted' => null]);
    aturPengingat($s, ['mode' => 'api', 'api_phone_number_id' => '109876', 'kinds' => ['h1' => ['enabled' => true, 'template' => '', 'api_template' => 'pengingat_h1'], 'h0' => ['enabled' => true, 'template' => '', 'api_template' => ''], 'recall' => ['enabled' => true, 'template' => '', 'api_template' => '']]]);
    jalankanPengingat();
    expect(pengingatDb()[0]->status)->toBe('skipped')->and(pengingatDb()[0]->last_error)->toContain('nomor HP');
    Http::assertNothingSent();
});

it('P2-03 mode tautan tidak memanggil API dan pengingat klinik lain tidak terlihat', function () {
    $s = siapPengingat();
    Http::swap(new Factory);
    Http::fake();

    jalankanPengingat();

    Http::assertNothingSent();
    $lain = makeClinic('Klinik Lain');
    $this->actingAs(makeUser(Role::Resepsionis, $lain))->get('/reminders')->assertInertia(fn ($p) => $p->has('pending', 0)->has('history', 0));
    $this->postJson('/internal-api/reminders/'.pengingatDb()[0]->id.'/link')->assertStatus(422);
});
