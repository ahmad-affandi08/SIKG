<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Domain\Role;
use Src\Scheduling\Application\AppointmentReminders;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 10:00:00', 'Asia/Jakarta'));
    rekamKirim();
});

afterEach(function () {
    Carbon::setTestNow();
});

function kirimPengingat(array $s): array
{
    tenant()->set($s['klinik']->id);

    return app(AppointmentReminders::class)->run();
}

it('JDW-07 pasien yang setuju menerima pengingat H-1 lewat email dan Telegram tanpa data klinis', function () {
    $s = siapJadwal();
    bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);
    setujuiPengingat($s);

    $hasil = kirimPengingat($s);

    expect($hasil)->toBe(['sent' => 2, 'failed' => 0])
        ->and(KirimPerekam::$email)->toHaveCount(1)
        ->and(KirimPerekam::$email[0]['to'])->toBe('budi@contoh.test')
        ->and(KirimPerekam::$telegram)->toHaveCount(1)
        ->and(KirimPerekam::$telegram[0]['chat'])->toBe('5550001');
    foreach ([KirimPerekam::$email[0]['body'], KirimPerekam::$telegram[0]['text']] as $isi) {
        expect($isi)->toContain('Klinik Uji')->toContain('besok, 17/06/2025 pukul 09:00')->toContain('drg. Sari')
            ->and($isi)->not->toContain('Sakit gigi')->and($isi)->not->toContain('Budi');
    }
});

it('JDW-07 pengingat tidak terkirim dua kali walau perintah dijalankan berulang', function () {
    $s = siapJadwal();
    bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);
    setujuiPengingat($s);

    kirimPengingat($s);
    $ulang = kirimPengingat($s);

    expect($ulang)->toBe(['sent' => 0, 'failed' => 0])->and(KirimPerekam::$email)->toHaveCount(1)->and(DB::table('appointment_reminders')->count())->toBe(2);
});

it('JDW-07 pengingat H-1 menunggu pukul 09.00 dan pengingat hari kunjungan memakai kalimat hari ini', function () {
    $s = siapJadwal();
    bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);
    setujuiPengingat($s);

    majuKe('2025-06-16 08:30:00');
    expect(kirimPengingat($s)['sent'])->toBe(0);

    majuKe('2025-06-17 07:00:00');
    expect(kirimPengingat($s)['sent'])->toBe(2)->and(KirimPerekam::$telegram[0]['text'])->toContain('hari ini pukul 09:00');
    expect(DB::table('appointment_reminders')->pluck('kind')->unique()->values()->all())->toBe(['h0']);
});

it('JDW-07 tanpa persetujuan, tanpa kontak, janji batal, atau pengingat dimatikan klinik tidak ada yang dikirim', function () {
    $s = siapJadwal();
    $id = bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);

    expect(kirimPengingat($s)['sent'])->toBe(0);

    setujuiPengingat($s, optIn: false);
    expect(kirimPengingat($s)['sent'])->toBe(0);

    setujuiPengingat($s);
    $s['klinik']->forceFill(['settings' => ['reminders_enabled' => false]])->save();
    expect(kirimPengingat($s)['sent'])->toBe(0);

    $s['klinik']->forceFill(['settings' => ['reminders_enabled' => true]])->save();
    DB::table('appointments')->where('id', $id)->update(['status' => 'cancelled']);
    expect(kirimPengingat($s)['sent'])->toBe(0)->and(KirimPerekam::$email)->toBe([]);
});

it('JDW-07 saluran yang belum dikonfigurasi dilewati dan saluran lain tetap terkirim', function () {
    rekamKirim(email: false, telegram: true);
    $s = siapJadwal();
    bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);
    setujuiPengingat($s);

    expect(kirimPengingat($s))->toBe(['sent' => 1, 'failed' => 0])->and(KirimPerekam::$email)->toBe([])->and(KirimPerekam::$telegram)->toHaveCount(1);
});

it('JDW-07 pengiriman gagal dicoba lagi sampai tiga kali lalu berhenti', function () {
    $s = siapJadwal();
    bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);
    setujuiPengingat($s, chat: null);
    KirimPerekam::$gagal = true;

    expect(kirimPengingat($s)['failed'])->toBe(1)->and(kirimPengingat($s)['failed'])->toBe(1)->and(kirimPengingat($s)['failed'])->toBe(1)
        ->and(kirimPengingat($s))->toBe(['sent' => 0, 'failed' => 0])
        ->and((int) DB::table('appointment_reminders')->value('attempts'))->toBe(3);

    KirimPerekam::$gagal = false;
    expect(kirimPengingat($s)['sent'])->toBe(0);
});

it('JDW-07 perintah terjadwal mengirim pengingat untuk setiap klinik', function () {
    $s = siapJadwal();
    bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);
    setujuiPengingat($s);
    tenant()->clear();

    $this->artisan('sikg:appointments:remind')->expectsOutputToContain('Pengingat terkirim: 2')->assertSuccessful();
});

it('JDW-07 catatan pengingat tidak menyimpan isi pesan maupun tujuan', function () {
    $s = siapJadwal();
    bukaJanji($s, ['date' => SELASA, 'time' => '09:00']);
    setujuiPengingat($s);
    kirimPengingat($s);

    $baris = json_encode(DB::table('appointment_reminders')->get());
    expect($baris)->not->toContain('contoh.test')->and($baris)->not->toContain('5550001')->and($baris)->not->toContain('Klinik Uji');
});

it('JDW-07 kontak pasien dienkripsi, persetujuan butuh saluran, dan perubahan tercatat tanpa nilai', function () {
    $s = siapJadwal();
    $this->actingAs($s['user']);

    $this->put("/patients/{$s['pasien']}/contact", ['email' => 'budi@contoh.test', 'reminders_opt_in' => true])->assertSessionHasNoErrors()->assertSessionHas('success');

    $baris = DB::table('patient_contacts')->first();
    expect($baris->email_encrypted)->not->toContain('budi')->and((bool) $baris->reminders_opt_in)->toBeTrue()->and($baris->opt_in_at)->not->toBeNull();
    $audit = json_encode(DB::table('audit_logs')->where('action', 'patient.contact_updated')->get());
    expect($audit)->toContain('patient.contact_updated')->and($audit)->not->toContain('budi');

    $this->get("/patients/{$s['pasien']}/contact")->assertOk()->assertInertia(fn ($page) => $page->component('Patients/Contact')
        ->where('contact.email', 'budi@contoh.test')->where('contact.reminders_opt_in', true)->where('contact.telegram_available', true));
});

it('JDW-07 persetujuan pengingat ditolak bila pasien tidak punya email maupun Telegram', function () {
    $s = siapJadwal();

    $this->actingAs($s['user'])->put("/patients/{$s['pasien']}/contact", ['email' => '', 'reminders_opt_in' => true])
        ->assertSessionHas('error_code', 'patient.contact_no_channel');
    expect(DB::table('patient_contacts')->count())->toBe(0);
});

it('JDW-07 email yang tidak valid ditolak', function () {
    $s = siapJadwal();

    $this->actingAs($s['user'])->put("/patients/{$s['pasien']}/contact", ['email' => 'bukan-email', 'reminders_opt_in' => false])
        ->assertSessionHasErrors('email');
});

it('JDW-07 hanya pemegang izin ubah pasien yang dapat mengelola kontak', function () {
    $s = siapJadwal();
    $perawat = makeUser(Role::Perawat, $s['klinik']);

    $this->actingAs($perawat)->get("/patients/{$s['pasien']}/contact")->assertForbidden();
    $this->actingAs($perawat)->put("/patients/{$s['pasien']}/contact", ['email' => 'a@b.test', 'reminders_opt_in' => false])->assertForbidden();
    $this->actingAs($perawat)->post("/patients/{$s['pasien']}/contact/telegram-link")->assertForbidden();
});

it('JDW-07 pasien menautkan Telegram lewat tautan sekali pakai yang hanya tersimpan sebagai hash', function () {
    $s = siapJadwal();
    $this->actingAs($s['user'])->post("/patients/{$s['pasien']}/contact/telegram-link")->assertRedirect("/patients/{$s['pasien']}/contact");

    $url = (string) session('telegram_link.url');
    expect($url)->toStartWith('https://t.me/klinik_bot?start=');
    $token = substr($url, strlen('https://t.me/klinik_bot?start='));
    $baris = DB::table('patient_contacts')->first();
    expect($baris->telegram_link_hash)->toBe(hash('sha256', $token))->and(json_encode($baris))->not->toContain($token);

    MasukPerekam::$pesan = [['update_id' => 10, 'chat_id' => '5550001', 'text' => '/start '.$token]];
    $this->artisan('sikg:telegram:poll')->expectsOutputToContain('Akun tertaut: 1')->assertSuccessful();

    $baris = DB::table('patient_contacts')->first();
    expect($baris->telegram_chat_id_encrypted)->not->toBeNull()->and($baris->telegram_chat_id_encrypted)->not->toContain('5550001')
        ->and($baris->telegram_link_hash)->toBeNull()
        ->and(KirimPerekam::$telegram[0]['chat'])->toBe('5550001')->and(KirimPerekam::$telegram[0]['text'])->toContain('sudah tertaut');
    expect(DB::table('audit_logs')->where('action', 'patient.telegram_linked')->count())->toBe(1);
});

it('JDW-07 tautan Telegram tidak bisa dipakai dua kali atau setelah kedaluwarsa dan tidak ada yang tertaut', function () {
    $s = siapJadwal();
    $this->actingAs($s['user'])->post("/patients/{$s['pasien']}/contact/telegram-link");
    $token = substr((string) session('telegram_link.url'), strlen('https://t.me/klinik_bot?start='));

    majuKe('2025-06-24 10:00:00');
    MasukPerekam::$pesan = [['update_id' => 1, 'chat_id' => '777', 'text' => '/start '.$token]];
    $this->artisan('sikg:telegram:poll')->expectsOutputToContain('Akun tertaut: 0')->assertSuccessful();

    expect(DB::table('patient_contacts')->value('telegram_chat_id_encrypted'))->toBeNull()
        ->and(KirimPerekam::$telegram[0]['text'])->toContain('tidak berlaku');
});

it('JDW-07 polling mengingat offset dan mengabaikan pesan selain perintah mulai', function () {
    rekamKirim();
    MasukPerekam::$pesan = [
        ['update_id' => 5, 'chat_id' => '1', 'text' => 'halo'],
        ['update_id' => 6, 'chat_id' => '', 'text' => ''],
    ];
    $this->artisan('sikg:telegram:poll')->assertSuccessful();
    expect(MasukPerekam::$offsetDiminta)->toBe(0)->and(KirimPerekam::$telegram)->toHaveCount(1);

    $this->artisan('sikg:telegram:poll')->assertSuccessful();
    expect(MasukPerekam::$offsetDiminta)->toBe(7);
});

it('JDW-07 melepas Telegram mematikan persetujuan bila tidak ada email tersisa', function () {
    $s = siapJadwal();
    setujuiPengingat($s, email: null);
    $this->actingAs($s['user'])->delete("/patients/{$s['pasien']}/contact/telegram")->assertSessionHas('success');

    $baris = DB::table('patient_contacts')->first();
    expect($baris->telegram_chat_id_encrypted)->toBeNull()->and((bool) $baris->reminders_opt_in)->toBeFalse();
});

it('JDW-07 tanpa bot Telegram tautan tidak dapat dibuat', function () {
    rekamKirim(telegram: false);
    $s = siapJadwal();

    $this->actingAs($s['user'])->post("/patients/{$s['pasien']}/contact/telegram-link")->assertSessionHas('error_code', 'patient.telegram_not_configured');
});
