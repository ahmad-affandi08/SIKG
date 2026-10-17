<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Scheduling\Application\AppointmentDigest;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 10:00:00', 'Asia/Jakarta'));
    rekamKirim();
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Notifikasi ringkasan saja; pemesanan janji ikut menerbitkan pemberitahuan "Janji baru". */
function ringkasan(): Builder
{
    return DB::table('notifications')->where('kind', 'appointment.digest');
}

function terbitkanRingkasan(array $s): int
{
    tenant()->set($s['klinik']->id);

    return app(AppointmentDigest::class)->raise();
}

it('JDW-07 ringkasan janji besok terbit untuk pemilik setelah pukul 09.00 tanpa nama pasien', function () {
    $s = siapJadwal();
    bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);
    $s['klinik']->forceFill(['settings' => ['notify_email' => 'pemilik@contoh.test', 'telegram_chat_id' => '-100123']])->save();

    expect(terbitkanRingkasan($s))->toBe(1);

    $baris = ringkasan()->first();
    expect($baris->title)->toBe('Janji besok')->and($baris->body)->toContain('1 janji besok')->toContain('17/06/2025')->toContain('pukul 09:00')
        ->and($baris->body)->not->toContain('Budi')->and($baris->permission)->toBe('appointment.view')
        ->and(KirimPerekam::$email[0]['to'])->toBe('pemilik@contoh.test')->and(KirimPerekam::$telegram[0]['chat'])->toBe('-100123');
});

it('JDW-07 ringkasan terbit sekali per hari walau perintah dijalankan berulang', function () {
    $s = siapJadwal();
    bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);

    terbitkanRingkasan($s);

    expect(terbitkanRingkasan($s))->toBe(0)->and(ringkasan()->count())->toBe(1);
});

it('JDW-07 ringkasan menunggu jamnya: besok mulai 09.00 dan hari ini mulai 07.00', function () {
    $s = siapJadwal();
    bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);

    majuKe('2025-06-16 08:30:00');
    expect(terbitkanRingkasan($s))->toBe(0);

    majuKe('2025-06-17 06:59:00');
    expect(terbitkanRingkasan($s))->toBe(0);

    majuKe('2025-06-17 07:00:00');
    expect(terbitkanRingkasan($s))->toBe(1)->and(ringkasan()->value('title'))->toBe('Janji hari ini');
});

it('JDW-07 janji batal atau sudah lewat tidak dihitung dan klinik dapat mematikan ringkasan', function () {
    $s = siapJadwal();
    $id = bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);

    DB::table('appointments')->where('id', $id)->update(['status' => 'cancelled']);
    expect(terbitkanRingkasan($s))->toBe(0);

    DB::table('appointments')->where('id', $id)->update(['status' => 'scheduled']);
    $s['klinik']->forceFill(['settings' => ['reminders_enabled' => false]])->save();
    expect(terbitkanRingkasan($s))->toBe(0);

    $s['klinik']->forceFill(['settings' => ['reminders_enabled' => true]])->save();
    majuKe('2025-06-17 09:30:00');
    expect(terbitkanRingkasan($s))->toBe(0)->and(ringkasan()->count())->toBe(0);
});

it('JDW-07 perintah terjadwal menerbitkan ringkasan untuk setiap klinik', function () {
    $s = siapJadwal();
    bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);
    tenant()->clear();

    $this->artisan('sikg:appointments:digest')->expectsOutputToContain('Ringkasan janji baru diterbitkan: 1')->assertSuccessful();

    $this->actingAs($s['user'])->getJson('/internal-api/notifications')->assertOk()->assertJsonPath('data.items.0.title', 'Janji besok');
});
