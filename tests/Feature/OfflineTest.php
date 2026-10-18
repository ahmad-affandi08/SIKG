<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Domain\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function jumlahCatatan(string $kunjungan): int
{
    return DB::table('clinical_notes')->where('encounter_id', $kunjungan)->count();
}

it('P3-02 halaman antrean offline hanya untuk pengguna masuk dan tidak memuat data server', function () {
    $s = siapKunjungan();

    auth()->logout();
    $this->get('/offline/queue')->assertRedirect('/login');
    $this->actingAs($s['dokter_user'])->get('/offline/queue')->assertOk()->assertInertia(fn ($p) => $p->component('Offline/Queue'));
});

it('P3-02 pengiriman ulang dengan kunci offline yang sama hanya mencatat sekali', function () {
    $s = siapKunjungan();
    $url = "/encounters/{$s['kunjungan']}/notes";
    $kunci = '3f1c2b7e-8d54-4c5e-9a67-1b2c3d4e5f60';

    $this->actingAs($s['dokter_user'])->putJson($url, ['subjective' => 'Nyeri gigi 46'], ['X-Offline-Key' => $kunci])->assertRedirect();
    $sesudahPertama = jumlahCatatan($s['kunjungan']);
    expect($sesudahPertama)->toBeGreaterThan(0);

    $ulang = $this->putJson($url, ['subjective' => 'Nyeri gigi 46'], ['X-Offline-Key' => $kunci])->assertOk()->assertJson(['ok' => true, 'duplicate' => true]);
    expect(jumlahCatatan($s['kunjungan']))->toBe($sesudahPertama);

    // Kunci lain (pengiriman baru) tetap diproses.
    $this->putJson($url, ['subjective' => 'Nyeri berkurang'], ['X-Offline-Key' => '4a2d3c8f-9e65-4d6f-8b78-2c3d4e5f6071'])->assertRedirect();
    expect(jumlahCatatan($s['kunjungan']))->toBeGreaterThan($sesudahPertama);
});

it('P3-02 permintaan yang gagal tidak menghabiskan kunci sehingga perbaikan dapat dikirim ulang', function () {
    $s = siapKunjungan();
    $url = "/encounters/{$s['kunjungan']}/notes";
    $kunci = '5b3e4d9a-0f76-4e70-9c89-3d4e5f607182';

    $this->actingAs($s['dokter_user'])->putJson($url, ['subjective' => str_repeat('a', 8001)], ['X-Offline-Key' => $kunci])->assertStatus(422);
    expect(jumlahCatatan($s['kunjungan']))->toBe(0);

    $this->putJson($url, ['subjective' => 'Isi yang benar'], ['X-Offline-Key' => $kunci])->assertRedirect();
    expect(jumlahCatatan($s['kunjungan']))->toBeGreaterThan(0);
});

it('P3-02 kunci offline tidak berlaku lintas pengguna, dan kunci tak sah diabaikan', function () {
    $s = siapKunjungan();
    $url = "/encounters/{$s['kunjungan']}/anamnesis";
    $kunci = '6c4f5eab-1a87-4f81-8d9a-4e5f60718293';

    $this->actingAs($s['dokter_user'])->putJson($url, ['chief_complaint' => 'Sakit gigi'], ['X-Offline-Key' => $kunci])->assertRedirect();
    $lain = makeUser(Role::Dokter, $s['klinik']);
    $this->actingAs($lain)->putJson($url, ['chief_complaint' => 'Sakit gigi lagi'], ['X-Offline-Key' => $kunci])->assertRedirect();

    // Kunci bukan UUID dianggap tidak ada: pengulangan tetap diproses (perilaku bawaan).
    $this->actingAs($s['dokter_user'])->putJson($url, ['chief_complaint' => 'A'], ['X-Offline-Key' => 'bukan-uuid'])->assertRedirect();
    $this->putJson($url, ['chief_complaint' => 'B'], ['X-Offline-Key' => 'bukan-uuid'])->assertRedirect();
});

it('P3-02 manifest PWA lengkap dan ikonnya ada dengan ukuran yang dijanjikan', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest)->toHaveKeys(['name', 'short_name', 'start_url', 'scope', 'display', 'icons', 'theme_color', 'lang'])
        ->and($manifest['display'])->toBe('standalone')->and($manifest['lang'])->toBe('id')->and($manifest['start_url'])->toBe('/dashboard');
    foreach ($manifest['icons'] as $ikon) {
        [$lebar, $tinggi] = getimagesize(public_path(ltrim($ikon['src'], '/')));
        expect($ikon['sizes'])->toBe("{$lebar}x{$tinggi}");
    }
    expect(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192')->toContain('512x512');
});

it('P3-02 service worker hanya menyimpan aset bundel dan halaman offline, tidak pernah data atau halaman pasien', function () {
    $sw = (string) file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("startsWith('/build/')")->and($sw)->toContain('/offline.html')->and($sw)->toContain("request.method !== 'GET'")
        ->and($sw)->toContain('url.origin !== self.location.origin');
    // Satu-satunya penulisan ke cache selain precache shell adalah aset /build/.
    expect(substr_count($sw, 'cache.put('))->toBe(1);
    $sebelumPut = substr($sw, 0, (int) strpos($sw, 'cache.put('));
    expect($sebelumPut)->toContain("startsWith('/build/')");
    expect($sw)->not->toContain('localStorage')->and($sw)->not->toContain('X-Inertia');
});

it('P3-02 halaman offline statis tanpa skrip luar dan peramban diberi tahu manifest', function () {
    $html = (string) file_get_contents(public_path('offline.html'));

    expect($html)->toContain('Tidak ada koneksi')->and($html)->not->toContain('<script')->and($html)->not->toContain('http://')->and($html)->not->toContain('https://');

    $login = $this->get('/login')->assertOk()->getContent();
    expect($login)->toContain('rel="manifest"')->and($login)->toContain('/manifest.webmanifest')->and($login)->toContain('name="theme-color"');
});
