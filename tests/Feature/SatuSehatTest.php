<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Application\PiiProtector;

const STG_AUTH = 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1';
const STG_FHIR = 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1';
const NIK_PASIEN = '9999010101900001';
const NIK_DOKTER = '9999010101900002';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
    Cache::flush();
});

afterEach(function () {
    Carbon::setTestNow();
});

function pasienBerNik(string $pasien, string $nik = NIK_PASIEN): void
{
    $pii = app(PiiProtector::class);
    DB::table('patients')->where('id', $pasien)->update(['nik_encrypted' => $pii->encrypt($nik), 'nik_hash' => $pii->blindIndex('nik', $nik)]);
}

/** @return array<string, mixed> kunjungan terbuka dengan satu diagnosa dan admin yang mengatur integrasi */
function siapSatuSehat(bool $aktif = true, bool $nikDokter = true, bool $nikPasien = true): array
{
    $s = siapKunjungan();
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    if ($nikPasien) {
        pasienBerNik($s['pasien']);
    }
    test()->actingAs($s['admin']);
    if ($aktif) {
        test()->put('/settings/satusehat', pengaturanSatuSehat())->assertSessionHasNoErrors();
    }
    if ($nikDokter) {
        test()->put("/settings/satusehat/doctors/{$s['dokter']->id}", ['nik' => NIK_DOKTER])->assertSessionHasNoErrors();
    }

    return $s;
}

function pengaturanSatuSehat(array $o = []): array
{
    return ['enabled' => true, 'environment' => 'staging', 'organization_id' => 'ORG-1', 'location_id' => 'LOC-1', 'location_name' => 'Poli Gigi', 'client_id' => 'client-uji', 'client_secret' => 'rahasia-uji-123', ...$o];
}

function tutupKunjunganSatuSehat(array $s): void
{
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()])->assertSessionHasNoErrors();
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
}

/** @param array<string, mixed> $o */
function palsuSatuSehat(array $o = []): void
{
    // Http::fake menumpuk stub lama (yang pertama cocok menang); mulai dari pabrik bersih agar tiap panggilan menggantikan.
    Http::swap(new Factory);
    Http::fake([
        STG_AUTH.'/accesstoken*' => $o['token'] ?? Http::response(['access_token' => 'token-uji', 'expires_in' => '3599']),
        STG_FHIR.'/Patient*' => $o['pasien'] ?? Http::response(['resourceType' => 'Bundle', 'entry' => [['resource' => ['id' => 'P0001']]]]),
        STG_FHIR.'/Practitioner*' => $o['dokter'] ?? Http::response(['resourceType' => 'Bundle', 'entry' => [['resource' => ['id' => 'N0001']]]]),
        STG_FHIR => $o['kirim'] ?? Http::response(['resourceType' => 'Bundle', 'type' => 'transaction-response', 'entry' => [
            ['response' => ['status' => '201 Created', 'resourceType' => 'Encounter', 'resourceID' => 'ENC-IHS-1']],
            ['response' => ['status' => '201 Created', 'resourceType' => 'Condition', 'resourceID' => 'COND-IHS-1']],
        ]]),
    ]);
}

function kirimSatuSehat(): void
{
    test()->artisan('sikg:satusehat:send')->assertSuccessful();
}

it('P2-01 pengaturan disimpan dengan rahasia terenkripsi dan tidak pernah dikirim kembali ke layar', function () {
    $s = siapSatuSehat(nikDokter: false);

    $baris = DB::table('satusehat_settings')->first();
    expect($baris->client_secret_encrypted)->not->toBeNull()->and($baris->client_secret_encrypted)->not->toContain('rahasia-uji-123')
        ->and((bool) $baris->enabled)->toBeTrue();
    $halaman = $this->get('/settings/satusehat')->assertOk();
    $halaman->assertInertia(fn ($p) => $p->component('Settings/Satusehat/Index')->where('settings.has_secret', true)->where('settings.complete', true)->where('settings.client_id', 'client-uji')->missing('settings.client_secret'));
    expect($halaman->getContent())->not->toContain('rahasia-uji-123');
    $audit = json_encode(DB::table('audit_logs')->where('action', 'satusehat.settings_updated')->get());
    expect($audit)->not->toContain('rahasia-uji-123')->and($audit)->toContain('secret_changed');

    $this->put('/settings/satusehat', pengaturanSatuSehat(['client_secret' => '', 'client_id' => 'client-baru']))->assertSessionHasNoErrors();
    expect(DB::table('satusehat_settings')->value('client_secret_encrypted'))->toBe($baris->client_secret_encrypted)->and(DB::table('satusehat_settings')->count())->toBe(1);
});

it('P2-01 mengaktifkan integrasi menuntut isian wajib terisi', function () {
    $s = siapSatuSehat(aktif: false, nikDokter: false);

    $this->put('/settings/satusehat', pengaturanSatuSehat(['organization_id' => '', 'client_secret' => '']))->assertSessionHas('error_code', 'satusehat.invalid_settings');
    $this->put('/settings/satusehat', pengaturanSatuSehat(['environment' => 'lain']))->assertSessionHasErrors('environment');
    $this->put('/settings/satusehat', pengaturanSatuSehat(['enabled' => false, 'organization_id' => '', 'client_secret' => '']))->assertSessionHasNoErrors();
    expect(DB::table('satusehat_settings')->count())->toBe(1);
});

it('P2-01 hanya pemegang izin pengaturan yang mengelola SATUSEHAT', function () {
    $s = siapSatuSehat();

    foreach ([Role::Dokter, Role::Perawat, Role::Resepsionis, Role::Kasir] as $peran) {
        $this->actingAs(makeUser($peran, $s['klinik']))->get('/settings/satusehat')->assertForbidden();
        $this->put('/settings/satusehat', pengaturanSatuSehat())->assertForbidden();
        $this->post('/settings/satusehat/test')->assertForbidden();
    }
    $this->actingAs(makeUser(Role::Pemilik, $s['klinik']))->get('/settings/satusehat')->assertOk();
});

it('P2-01 uji koneksi melaporkan berhasil atau kredensial ditolak tanpa membocorkan rahasia', function () {
    $s = siapSatuSehat();

    palsuSatuSehat();
    $this->post('/settings/satusehat/test')->assertSessionHas('success', 'Koneksi ke SATUSEHAT berhasil.');

    palsuSatuSehat(['token' => Http::response(['error' => 'invalid_client'], 401)]);
    $this->post('/settings/satusehat/test')->assertSessionHas('error', 'Client ID atau client secret ditolak SATUSEHAT.');

    palsuSatuSehat(['token' => Http::response('', 503)]);
    $this->post('/settings/satusehat/test')->assertSessionHas('error');
    expect(json_encode(DB::table('audit_logs')->get()))->not->toContain('rahasia-uji-123');
});

it('P2-01 NIK dokter terenkripsi, harus 16 digit, dan tidak muncul di audit atau layar', function () {
    $s = siapSatuSehat(nikDokter: false);

    $this->put("/settings/satusehat/doctors/{$s['dokter']->id}", ['nik' => '123'])->assertSessionHas('error_code', 'satusehat.invalid_settings');
    $this->put("/settings/satusehat/doctors/{$s['dokter']->id}", ['nik' => '9999 0101 0190 0002'])->assertSessionHasNoErrors();

    $baris = DB::table('satusehat_practitioners')->first();
    expect($baris->nik_encrypted)->not->toContain(NIK_DOKTER)->and(app(PiiProtector::class)->decrypt($baris->nik_encrypted))->toBe(NIK_DOKTER);
    expect(json_encode(DB::table('audit_logs')->where('action', 'satusehat.practitioner_nik_updated')->get()))->not->toContain(NIK_DOKTER);
    $halaman = $this->get('/settings/satusehat');
    $halaman->assertInertia(fn ($p) => $p->where('doctors.0.has_nik', true));
    expect($halaman->getContent())->not->toContain(NIK_DOKTER);

    $this->put("/settings/satusehat/doctors/{$s['dokter']->id}", ['nik' => ''])->assertSessionHasNoErrors();
    expect(DB::table('satusehat_practitioners')->value('nik_encrypted'))->toBeNull();
});

it('P2-01 menutup kunjungan memasukkannya ke antrean hanya bila integrasi aktif, sekali per kunjungan', function () {
    $mati = siapSatuSehat(aktif: false);
    tutupKunjunganSatuSehat($mati);
    expect(DB::table('satusehat_submissions')->count())->toBe(0);

    $s = siapSatuSehat();
    tutupKunjunganSatuSehat($s);
    expect(DB::table('satusehat_submissions')->where('encounter_id', $s['kunjungan'])->where('status', 'pending')->count())->toBe(1);
});

it('P2-01 antrean dikirim sebagai Bundle FHIR, ID dicatat, dan tidak dikirim dua kali', function () {
    $s = siapSatuSehat();
    $tindakan = tindakan($s, ['icd9_code' => '23.2']);
    $this->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $tindakan->id, 'tooth_fdi' => 46, 'quantity' => 1])->assertSessionHasNoErrors();
    tutupKunjunganSatuSehat($s);
    palsuSatuSehat();

    kirimSatuSehat();

    $baris = DB::table('satusehat_submissions')->first();
    expect($baris->status)->toBe('sent')->and($baris->encounter_ihs_id)->toBe('ENC-IHS-1')->and($baris->attempts)->toBe(1)->and($baris->last_error)->toBeNull();
    expect(DB::table('satusehat_patients')->value('ihs_id'))->toBe('P0001')->and(DB::table('satusehat_practitioners')->value('ihs_id'))->toBe('N0001');

    Http::assertSent(function (Request $request) {
        if ($request->method() !== 'POST' || $request->url() !== STG_FHIR) {
            return false;
        }
        $data = $request->data();
        $tipe = array_map(fn ($e) => $e['resource']['resourceType'], $data['entry']);

        return $request->hasHeader('Authorization', 'Bearer token-uji') && $tipe === ['Encounter', 'Condition', 'Procedure']
            && $data['entry'][0]['resource']['subject']['reference'] === 'Patient/P0001'
            && $data['entry'][1]['resource']['code']['coding'][0]['code'] === 'K02.1'
            && $data['entry'][2]['resource']['code']['coding'][0]['code'] === '23.2';
    });

    Http::swap(new Factory);
    Http::fake();
    kirimSatuSehat();
    Http::assertNothingSent();
});

it('P2-01 tindakan tanpa kode ICD-9-CM tidak dikirim sebagai Procedure', function () {
    $s = siapSatuSehat();
    $tindakan = tindakan($s);
    $this->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $tindakan->id, 'tooth_fdi' => 46, 'quantity' => 1]);
    tutupKunjunganSatuSehat($s);
    palsuSatuSehat();

    kirimSatuSehat();

    Http::assertSent(fn (Request $r) => $r->url() === STG_FHIR && count($r->data()['entry']) === 2);
});

it('P2-01 token dipakai ulang antar permintaan dan diperbarui sekali bila ditolak', function () {
    $s = siapSatuSehat();
    tutupKunjunganSatuSehat($s);
    palsuSatuSehat();

    kirimSatuSehat();

    Http::assertSentCount(4); // token, cari pasien, cari dokter, kirim bundel
    Http::assertSentCount(4);
    expect(collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'accesstoken'))->count())->toBe(1);

    Cache::flush();
    $s2 = siapSatuSehat();
    tutupKunjunganSatuSehat($s2);
    $panggilan = 0;
    Http::fake([
        STG_AUTH.'/accesstoken*' => Http::response(['access_token' => 'token-uji', 'expires_in' => '3599']),
        STG_FHIR.'/Patient*' => function () use (&$panggilan) {
            return ++$panggilan === 1 ? Http::response('', 401) : Http::response(['entry' => [['resource' => ['id' => 'P0002']]]]);
        },
        STG_FHIR.'/Practitioner*' => Http::response(['entry' => [['resource' => ['id' => 'N0002']]]]),
        STG_FHIR => Http::response(['entry' => [['response' => ['resourceType' => 'Encounter', 'resourceID' => 'E2']]]]),
    ]);
    kirimSatuSehat();
    expect(DB::table('satusehat_submissions')->where('encounter_id', $s2['kunjungan'])->value('status'))->toBe('sent');
});

it('P2-01 pasien tanpa NIK dilewati dengan alasan', function () {
    $s = siapSatuSehat(nikPasien: false);
    tutupKunjunganSatuSehat($s);
    palsuSatuSehat();

    kirimSatuSehat();

    $baris = DB::table('satusehat_submissions')->first();
    expect($baris->status)->toBe('skipped')->and($baris->last_error)->toContain('NIK');
    Http::assertNothingSent();
});

it('P2-01 NIK dokter yang belum diisi menandai perlu tindakan dan dapat dikirim ulang setelah diisi', function () {
    $s = siapSatuSehat(nikDokter: false);
    tutupKunjunganSatuSehat($s);
    palsuSatuSehat();

    kirimSatuSehat();
    $baris = DB::table('satusehat_submissions')->first();
    expect($baris->status)->toBe('blocked')->and($baris->last_error)->toContain('NIK dokter')->and($baris->last_error)->toContain('drg. Sari');

    kirimSatuSehat();
    expect(DB::table('satusehat_submissions')->value('status'))->toBe('blocked');

    $this->actingAs($s['admin'])->put("/settings/satusehat/doctors/{$s['dokter']->id}", ['nik' => NIK_DOKTER]);
    $this->post("/settings/satusehat/submissions/{$baris->id}/retry")->assertSessionHasNoErrors();
    kirimSatuSehat();

    expect(DB::table('satusehat_submissions')->value('status'))->toBe('sent');
});

it('P2-01 NIK yang tidak ditemukan di SATUSEHAT menandai perlu tindakan', function () {
    $s = siapSatuSehat();
    tutupKunjunganSatuSehat($s);
    palsuSatuSehat(['pasien' => Http::response(['resourceType' => 'Bundle', 'total' => 0, 'entry' => []])]);

    kirimSatuSehat();

    $baris = DB::table('satusehat_submissions')->first();
    expect($baris->status)->toBe('blocked')->and($baris->last_error)->toContain('NIK pasien tidak ditemukan');
});

it('P2-01 gangguan sementara dicoba ulang bertahap lalu gagal setelah lima percobaan', function () {
    $s = siapSatuSehat();
    tutupKunjunganSatuSehat($s);
    palsuSatuSehat(['kirim' => Http::response('', 503)]);

    kirimSatuSehat();
    $baris = DB::table('satusehat_submissions')->first();
    expect($baris->status)->toBe('pending')->and($baris->attempts)->toBe(1)->and($baris->last_error)->toContain('503');

    kirimSatuSehat(); // belum jatuh tempo
    expect(DB::table('satusehat_submissions')->value('attempts'))->toBe(1);

    foreach ([6, 21, 82, 330] as $menit) {
        Carbon::setTestNow(now()->addMinutes($menit));
        kirimSatuSehat();
    }
    $baris = DB::table('satusehat_submissions')->first();
    expect($baris->status)->toBe('failed')->and($baris->attempts)->toBe(5);

    palsuSatuSehat();
    $this->flushSession(); // lompatan jam melewati batas tidak aktif sesi
    $this->actingAs($s['admin'])->post("/settings/satusehat/submissions/{$baris->id}/retry")->assertSessionHasNoErrors();
    kirimSatuSehat();
    expect(DB::table('satusehat_submissions')->value('status'))->toBe('sent');
});

it('P2-01 penolakan permanen langsung gagal dan pesannya tidak memuat data pasien', function () {
    $s = siapSatuSehat();
    tutupKunjunganSatuSehat($s);
    palsuSatuSehat(['kirim' => Http::response(['resourceType' => 'OperationOutcome', 'issue' => [['severity' => 'error', 'code' => 'invalid', 'details' => ['text' => 'Budi Santoso '.NIK_PASIEN], 'expression' => ['Encounter.participant[0]']]]], 400)]);

    kirimSatuSehat();

    $baris = DB::table('satusehat_submissions')->first();
    expect($baris->status)->toBe('failed')->and($baris->last_error)->toContain('HTTP 400')->and($baris->last_error)->toContain('invalid pada Encounter.participant[0]')
        ->and($baris->last_error)->not->toContain('Budi')->and($baris->last_error)->not->toContain(NIK_PASIEN);
});

it('P2-01 integrasi yang dimatikan tidak mengirim apa pun walau antrean tersisa', function () {
    $s = siapSatuSehat();
    tutupKunjunganSatuSehat($s);
    $this->actingAs($s['admin'])->put('/settings/satusehat', pengaturanSatuSehat(['enabled' => false, 'client_secret' => '']))->assertSessionHasNoErrors();
    palsuSatuSehat();

    kirimSatuSehat();

    Http::assertNothingSent();
    expect(DB::table('satusehat_submissions')->value('status'))->toBe('pending');
});

it('P2-01 pemantauan hanya menampilkan nomor RM dan antrean klinik sendiri', function () {
    $s = siapSatuSehat();
    tutupKunjunganSatuSehat($s);
    $lain = siapSatuSehat();
    tutupKunjunganSatuSehat($lain);

    $halaman = $this->actingAs($s['admin'])->get('/settings/satusehat');
    $halaman->assertInertia(fn ($p) => $p->has('submissions', 1)->where('counts.pending', 1)->where('submissions.0.medical_record_no', fn ($rm) => str_starts_with($rm, 'UJI-')));
    expect($halaman->getContent())->not->toContain('Budi Santoso');
    $this->post('/settings/satusehat/submissions/'.DB::table('satusehat_submissions')->where('clinic_id', $lain['klinik']->id)->value('id').'/retry')->assertSessionHas('error_code', 'satusehat.invalid_settings');
});
