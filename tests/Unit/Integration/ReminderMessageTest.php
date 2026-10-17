<?php

declare(strict_types=1);

use Src\Integration\Domain\Reminder\ReminderKind;
use Src\Integration\Domain\Reminder\ReminderMessage;
use Src\Integration\Domain\Reminder\ReminderSettings;

it('P2-03 pesan dirakit dari templat dengan penanda terisi', function () {
    $pesan = ReminderMessage::render('Yth. {nama}, jadwal {tanggal} pukul {jam} dengan {dokter} di {klinik}.', ['nama' => 'Budi', 'tanggal' => '17/06/2025', 'jam' => '09:00', 'dokter' => 'drg. Sari', 'klinik' => 'Klinik Uji']);

    expect($pesan)->toBe('Yth. Budi, jadwal 17/06/2025 pukul 09:00 dengan drg. Sari di Klinik Uji.');
});

it('P2-03 penanda yang tidak diberi nilai menjadi kosong dan spasi ganda dirapikan', function () {
    expect(ReminderMessage::render('Halo {nama}  {bulan} bulan', ['nama' => 'Budi']))->toBe('Halo Budi bulan');
});

it('P2-03 penanda yang tidak dikenal dilaporkan', function () {
    expect(ReminderMessage::unknownPlaceholders('{nama} {salah} {tanggal} {salah} {lain}'))->toBe(['salah', 'lain'])
        ->and(ReminderMessage::unknownPlaceholders('Tanpa penanda'))->toBe([]);
});

it('P2-03 tautan WhatsApp memakai nomor internasional tanpa tanda dan pesan terkode', function () {
    $tautan = ReminderMessage::whatsappLink('+62 812-3456-789', 'Halo & selamat pagi');

    expect($tautan)->toBe('https://wa.me/628123456789?text=Halo%20%26%20selamat%20pagi');
});

it('P2-03 templat bawaan setiap jenis hanya memakai penanda yang dikenal', function () {
    foreach (ReminderKind::cases() as $jenis) {
        expect(ReminderMessage::unknownPlaceholders($jenis->defaultTemplate()))->toBe([], $jenis->value);
    }
});

it('P2-03 parameter templat API sesuai jenis dan setiap parameter punya penanda', function () {
    expect(ReminderKind::DayBefore->apiParameters())->toBe(['nama', 'tanggal', 'jam', 'dokter', 'klinik'])
        ->and(ReminderKind::Recall->apiParameters())->toBe(['nama', 'bulan', 'klinik'])
        ->and(array_diff([...ReminderKind::SameDay->apiParameters(), ...ReminderKind::Recall->apiParameters()], ReminderMessage::PLACEHOLDERS))->toBe([]);
});

it('P2-03 pengaturan memilih templat kustom atau bawaan dan menandai kesiapan API', function () {
    $s = new ReminderSettings(true, 'api', true, false, true, 6, ['h1' => '  ', 'h0' => 'Kustom {nama}'], '123', 'token', ['h1' => 'pengingat_h1'], 'id');

    expect($s->template(ReminderKind::DayBefore))->toBe(ReminderKind::DayBefore->defaultTemplate())
        ->and($s->template(ReminderKind::SameDay))->toBe('Kustom {nama}')
        ->and($s->kindEnabled(ReminderKind::SameDay))->toBeFalse()
        ->and($s->apiTemplate(ReminderKind::DayBefore))->toBe('pengingat_h1')->and($s->apiTemplate(ReminderKind::Recall))->toBeNull()
        ->and($s->isApi())->toBeTrue()->and($s->apiReady())->toBeTrue()
        ->and(ReminderSettings::empty()->apiReady())->toBeFalse();
});
