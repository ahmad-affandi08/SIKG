<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Src\Clinic\Infrastructure\Eloquent\HolidayModel;
use Src\IdentityAccess\Domain\Role;
use Src\Scheduling\Contracts\AppointmentProgress;
use Src\Scheduling\Domain\Events\PatientCheckedIn;
use Src\Scheduling\Infrastructure\Eloquent\AppointmentModel;
use Src\Shared\Application\ResourceLock;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('JDW-01 resepsionis membuat janji dan waktu tersimpan dalam UTC', function () {
    $s = siapJadwal();

    $id = bukaJanji($s);

    $a = AppointmentModel::query()->findOrFail($id);
    expect($a->status)->toBe('scheduled')->and($a->source)->toBe('booking')
        ->and($a->starts_at->format('Y-m-d H:i'))->toBe('2025-06-16 02:00')
        ->and($a->ends_at->format('H:i'))->toBe('02:30')
        ->and($a->complaint)->toBe('Sakit gigi');
    expect(AuditLogModel::query()->where('action', 'appointment.booked')->where('subject_id', $id)->exists())->toBeTrue();
});

it('JDW-02 menolak janji di luar jadwal praktik dokter', function () {
    $s = siapJadwal();

    foreach (['08:30', '11:45', '12:30', '17:00'] as $jam) {
        test()->actingAs($s['user'])->post('/appointments', janji($s, ['time' => $jam]))->assertSessionHasErrors('domain');
    }
    test()->actingAs($s['user'])->post('/appointments', janji($s, ['date' => '2025-06-18']))->assertSessionHasErrors('domain');
    tenant()->set($s['klinik']->id);
    expect(AppointmentModel::query()->count())->toBe(0);
});

it('JDW-02 menolak janji pada hari libur klinik dan tanggal yang sudah lewat', function () {
    $s = siapJadwal();
    tenant()->set($s['klinik']->id);
    (new HolidayModel)->forceFill(['date' => SELASA, 'name' => 'Libur uji'])->save();

    test()->actingAs($s['user'])->post('/appointments', janji($s, ['date' => SELASA]))->assertSessionHasErrors('domain');
    test()->actingAs($s['user'])->post('/appointments', janji($s, ['date' => '2025-06-09']))->assertSessionHasErrors('domain');
});

it('JDW-02 menolak janji dengan dokter atau kursi nonaktif dan pasien tidak dikenal', function () {
    $s = siapJadwal();
    $nonaktifDokter = makeDoctor($s['klinik'], 'drg. Cuti', [['weekday' => 1, 'start' => '09:00', 'end' => '12:00']], false);
    $nonaktifKursi = makeChair($s['klinik'], 'Rusak', false);

    test()->actingAs($s['user'])->post('/appointments', janji($s, ['doctor_id' => $nonaktifDokter->id]))->assertSessionHasErrors('domain');
    test()->actingAs($s['user'])->post('/appointments', janji($s, ['chair_id' => $nonaktifKursi->id]))->assertSessionHasErrors('domain');
    test()->actingAs($s['user'])->post('/appointments', janji($s, ['patient_id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ']))->assertSessionHasErrors('domain');
});

it('JDW-02 dokter yang sama tidak boleh ganda pada slot yang bertumpuk', function () {
    $s = siapJadwal();
    bukaJanji($s);
    $lain = makePatient($s['klinik'], 'Citra Lestari');

    test()->actingAs($s['user'])->post('/appointments', janji($s, ['patient_id' => $lain, 'chair_id' => null, 'time' => '09:15']))->assertSessionHasErrors('domain');
    tenant()->set($s['klinik']->id);
    expect(AppointmentModel::query()->count())->toBe(1);
});

it('JDW-02 janji berurutan tanpa tumpang tindih diterima', function () {
    $s = siapJadwal();
    bukaJanji($s);
    $lain = makePatient($s['klinik'], 'Citra Lestari');
    $kursi2 = makeChair($s['klinik'], 'Kursi 2');

    test()->actingAs($s['user'])->post('/appointments', janji($s, ['patient_id' => $lain, 'chair_id' => $kursi2->id, 'time' => '09:30']))->assertSessionHasNoErrors();
});

it('JDW-02 kursi yang sama tidak boleh dipakai dua dokter pada waktu bertumpuk', function () {
    $s = siapJadwal();
    bukaJanji($s);
    $dokter2 = makeDoctor($s['klinik'], 'drg. Budi', [['weekday' => 1, 'start' => '09:00', 'end' => '12:00']]);
    $lain = makePatient($s['klinik'], 'Citra Lestari');

    test()->actingAs($s['user'])->post('/appointments', janji($s, ['doctor_id' => $dokter2->id, 'patient_id' => $lain, 'time' => '09:00']))->assertSessionHasErrors('domain');
    test()->actingAs($s['user'])->post('/appointments', janji($s, ['doctor_id' => $dokter2->id, 'patient_id' => $lain, 'chair_id' => makeChair($s['klinik'], 'Kursi 2')->id, 'time' => '09:00']))->assertSessionHasNoErrors();
});

it('JDW-02 pasien yang sama tidak boleh punya dua janji bertumpuk', function () {
    $s = siapJadwal();
    bukaJanji($s);
    $dokter2 = makeDoctor($s['klinik'], 'drg. Budi', [['weekday' => 1, 'start' => '09:00', 'end' => '12:00']]);

    test()->actingAs($s['user'])->post('/appointments', janji($s, ['doctor_id' => $dokter2->id, 'chair_id' => null, 'time' => '09:10']))->assertSessionHasErrors('domain');
});

it('JDW-02 janji batal membebaskan slot', function () {
    $s = siapJadwal();
    $id = bukaJanji($s);
    test()->actingAs($s['user'])->post("/appointments/{$id}/cancel", ['reason' => 'Pasien berhalangan'])->assertSessionHasNoErrors();

    test()->actingAs($s['user'])->post('/appointments', janji($s))->assertSessionHasNoErrors();
});

it('JDW-02 konflik dilaporkan sebagai 409 dengan kode appointment.conflict untuk klien JSON', function () {
    $s = siapJadwal();
    bukaJanji($s);
    $lain = makePatient($s['klinik'], 'Citra Lestari');

    test()->actingAs($s['user'])->postJson('/appointments', janji($s, ['patient_id' => $lain]))
        ->assertStatus(409)->assertJsonPath('code', 'appointment.conflict');
});

it('JDW-02 pemesanan memegang kunci baris dokter, kursi, dan pasien', function () {
    $s = siapJadwal();
    bukaJanji($s);

    $kunci = DB::table('sequences')->where('name', 'lock')->pluck('scope')->all();
    expect($kunci)->toContain($s['klinik']->id.':doctor:'.$s['dokter']->id, $s['klinik']->id.':chair:'.$s['kursi']->id, $s['klinik']->id.':patient:'.$s['pasien']);
});

it('JDW-02 kunci sumber daya hanya dapat diambil di dalam transaksi', function () {
    $klinik = makeClinic();
    tenant()->set($klinik->id);
    $lock = app(ResourceLock::class);

    DB::rollBack();
    try {
        expect(fn () => $lock->acquire('doctor:X'))->toThrow(RuntimeException::class);
    } finally {
        DB::beginTransaction();
    }
});

it('JDW-08 jadwal ulang wajib beralasan dan tercatat sebelum dan sesudah', function () {
    $s = siapJadwal();
    $id = bukaJanji($s);

    test()->actingAs($s['user'])->put("/appointments/{$id}", janji($s, ['time' => '10:00', 'reason' => '']))->assertSessionHasErrors('reason');
    test()->actingAs($s['user'])->put("/appointments/{$id}", janji($s, ['time' => '10:00', 'reason' => 'Pasien minta diundur']))->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $a = AppointmentModel::query()->findOrFail($id);
    expect($a->starts_at->format('H:i'))->toBe('03:00')->and($a->reschedule_reason)->toBe('Pasien minta diundur')->and($a->version)->toBe(2);
    $log = AuditLogModel::query()->where('action', 'appointment.rescheduled')->firstOrFail();
    expect($log->before['starts_at'])->toBe('2025-06-16T02:00:00Z')->and($log->after['starts_at'])->toBe('2025-06-16T03:00:00Z')->and($log->after['reason'])->toBe('Pasien minta diundur');
});

it('JDW-08 jadwal ulang tetap menjaga bentrok dan mengabaikan janji itu sendiri', function () {
    $s = siapJadwal();
    $id = bukaJanji($s);
    $lain = makePatient($s['klinik'], 'Citra Lestari');
    test()->actingAs($s['user'])->post('/appointments', janji($s, ['patient_id' => $lain, 'chair_id' => null, 'time' => '10:00']))->assertSessionHasNoErrors();

    test()->actingAs($s['user'])->put("/appointments/{$id}", janji($s, ['time' => '10:15', 'reason' => 'Geser']))->assertSessionHasErrors('domain');
    test()->actingAs($s['user'])->put("/appointments/{$id}", janji($s, ['time' => '09:10', 'reason' => 'Geser sedikit']))->assertSessionHasNoErrors();
});

it('JDW-08 janji yang sudah check-in tidak dapat dijadwalkan ulang', function () {
    $s = siapJadwal();
    $id = bukaJanji($s);
    test()->actingAs($s['user'])->post("/appointments/{$id}/check-in");

    test()->actingAs($s['user'])->put("/appointments/{$id}", janji($s, ['time' => '10:00', 'reason' => 'Terlambat']))->assertSessionHasErrors('domain');
});

it('JDW-08 pembatalan wajib beralasan dan alasan tersimpan', function () {
    $s = siapJadwal();
    $id = bukaJanji($s);

    test()->actingAs($s['user'])->post("/appointments/{$id}/cancel", ['reason' => ''])->assertSessionHasErrors('reason');
    test()->actingAs($s['user'])->post("/appointments/{$id}/cancel", ['reason' => 'Sakit'])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $a = AppointmentModel::query()->findOrFail($id);
    expect($a->status)->toBe('cancelled')->and($a->cancel_reason)->toBe('Sakit');
    expect(AuditLogModel::query()->where('action', 'appointment.cancelled')->firstOrFail()->after['reason'])->toBe('Sakit');
});

it('JDW-04 alur status: konfirmasi, check-in dengan nomor antrean, lalu tidak bisa dibatalkan setelah diperiksa', function () {
    $s = siapJadwal();
    $id = bukaJanji($s);

    test()->actingAs($s['user'])->post("/appointments/{$id}/confirm")->assertSessionHasNoErrors();
    test()->actingAs($s['user'])->post("/appointments/{$id}/check-in")->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $a = AppointmentModel::query()->findOrFail($id);
    expect($a->status)->toBe('checked_in')->and($a->queue_no)->toBe(1)->and($a->queue_date->toDateString())->toBe(SENIN)->and($a->checked_in_at)->not->toBeNull();

    tenant()->set($s['klinik']->id);
    app(AppointmentProgress::class)->markInProgress($s['user']->id, $id, $s['kursi']->id);
    test()->actingAs($s['user'])->post("/appointments/{$id}/cancel", ['reason' => 'x'])->assertSessionHasErrors('domain');
    tenant()->set($s['klinik']->id);
    app(AppointmentProgress::class)->markCompleted($s['user']->id, $id);
    tenant()->set($s['klinik']->id);
    expect(AppointmentModel::query()->findOrFail($id)->status)->toBe('completed');
});

it('JDW-04 perubahan status yang tidak sah ditolak dengan 409', function () {
    $s = siapJadwal();
    $id = bukaJanji($s);

    test()->actingAs($s['user'])->postJson("/appointments/{$id}/cancel", ['reason' => 'a'])->assertRedirect();
    test()->actingAs($s['user'])->postJson("/appointments/{$id}/confirm")->assertStatus(409)->assertJsonPath('code', 'appointment.invalid_transition');
});

it('JDW-04 check-in hanya pada hari janji', function () {
    $s = siapJadwal();
    $id = bukaJanji($s, ['date' => SELASA]);

    test()->actingAs($s['user'])->post("/appointments/{$id}/check-in")->assertSessionHasErrors('domain');
});

it('JDW-04 tidak datang hanya setelah jam janji', function () {
    $s = siapJadwal();
    $id = bukaJanji($s);

    test()->actingAs($s['user'])->post("/appointments/{$id}/no-show")->assertSessionHasErrors('domain');
    majuKe('2025-06-16 09:45:00');
    test()->actingAs($s['user'])->post("/appointments/{$id}/no-show")->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    expect(AppointmentModel::query()->findOrFail($id)->status)->toBe('no_show');
});

it('JDW-05 pasien walk-in langsung check-in dengan nomor antrean harian berurutan', function () {
    $s = siapJadwal();
    $lain = makePatient($s['klinik'], 'Citra Lestari');

    test()->actingAs($s['user'])->post('/walk-ins', ['patient_id' => $s['pasien'], 'doctor_id' => $s['dokter']->id, 'complaint' => 'Gigi berlubang'])->assertSessionHasNoErrors();
    test()->actingAs($s['user'])->post('/walk-ins', ['patient_id' => $lain, 'doctor_id' => $s['dokter']->id])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $antrean = AppointmentModel::query()->orderBy('queue_no')->get();
    expect($antrean->pluck('queue_no')->all())->toBe([1, 2])
        ->and($antrean->pluck('status')->unique()->all())->toBe(['checked_in'])
        ->and($antrean->pluck('source')->unique()->all())->toBe(['walkin']);
});

it('JDW-05 nomor antrean mulai dari 1 lagi setiap hari dan terpisah antar klinik', function () {
    $s = siapJadwal();
    test()->actingAs($s['user'])->post('/walk-ins', ['patient_id' => $s['pasien'], 'doctor_id' => $s['dokter']->id]);

    majuKe('2025-06-17 08:00:00');
    test()->actingAs($s['user'])->post('/walk-ins', ['patient_id' => $s['pasien'], 'doctor_id' => $s['dokter']->id]);

    $b = siapJadwal();
    test()->actingAs($b['user'])->post('/walk-ins', ['patient_id' => $b['pasien'], 'doctor_id' => $b['dokter']->id]);

    tenant()->set($s['klinik']->id);
    expect(AppointmentModel::query()->orderBy('queue_date')->pluck('queue_no')->all())->toBe([1, 1]);
    tenant()->set($b['klinik']->id);
    expect(AppointmentModel::query()->pluck('queue_no')->all())->toBe([1]);
});

it('JDW-05 walk-in memerlukan dokter aktif dan pasien yang ada', function () {
    $s = siapJadwal();

    test()->actingAs($s['user'])->post('/walk-ins', ['patient_id' => $s['pasien']])->assertSessionHasErrors('doctor_id');
    test()->actingAs($s['user'])->post('/walk-ins', ['patient_id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ', 'doctor_id' => $s['dokter']->id])->assertSessionHasErrors('domain');
});

it('JDW-06 antrean harian lewat JSON berurut nomor dan tidak melintasi klinik', function () {
    $s = siapJadwal();
    $lain = makePatient($s['klinik'], 'Citra Lestari');
    test()->actingAs($s['user'])->post('/walk-ins', ['patient_id' => $s['pasien'], 'doctor_id' => $s['dokter']->id]);
    test()->actingAs($s['user'])->post('/walk-ins', ['patient_id' => $lain, 'doctor_id' => $s['dokter']->id]);

    $data = test()->actingAs($s['user'])->getJson('/internal-api/queue')->assertOk()->json('data');
    expect(array_column($data, 'queue_no'))->toBe([1, 2])
        ->and($data[0]['patient_name'])->toBe('Budi Santoso')->and($data[0]['doctor_name'])->toBe('drg. Sari')->and($data[0]['status_label'])->toBe('Check-in');

    $b = siapJadwal();
    test()->actingAs($b['user'])->getJson('/internal-api/queue')->assertOk()->assertJsonPath('data', []);
});

it('JDW-03 kalender harian dan mingguan, dapat disaring per dokter dan kursi', function () {
    $s = siapJadwal();
    bukaJanji($s);
    $dokter2 = makeDoctor($s['klinik'], 'drg. Budi', [['weekday' => 2, 'start' => '09:00', 'end' => '12:00']]);
    $lain = makePatient($s['klinik'], 'Citra Lestari');
    test()->actingAs($s['user'])->post('/appointments', janji($s, ['doctor_id' => $dokter2->id, 'patient_id' => $lain, 'chair_id' => null, 'date' => SELASA, 'time' => '10:00']))->assertSessionHasNoErrors();

    $harian = test()->actingAs($s['user'])->get('/schedule?date='.SENIN)->assertOk();
    $harian->assertInertia(fn ($p) => $p->component('Schedule/Index')->where('calendar.from', SENIN)->where('calendar.to', SENIN)->has('calendar.items', 1)->where('calendar.items.0.patient_name', 'Budi Santoso'));

    test()->actingAs($s['user'])->get('/schedule?view=week&date='.SELASA)
        ->assertInertia(fn ($p) => $p->where('calendar.from', SENIN)->where('calendar.to', '2025-06-22')->has('calendar.items', 2));
    test()->actingAs($s['user'])->get('/schedule?view=week&date='.SENIN.'&doctor='.$dokter2->id)
        ->assertInertia(fn ($p) => $p->has('calendar.items', 1)->where('calendar.items.0.doctor_name', 'drg. Budi'));
    test()->actingAs($s['user'])->get('/schedule?view=week&date='.SENIN.'&chair='.$s['kursi']->id)
        ->assertInertia(fn ($p) => $p->has('calendar.items', 1)->where('calendar.items.0.chair_name', 'Kursi 1'));
});

it('JDW-03 kalender hanya memuat janji klinik sendiri', function () {
    $s = siapJadwal();
    bukaJanji($s);
    $b = siapJadwal();

    test()->actingAs($b['user'])->get('/schedule?date='.SENIN)->assertInertia(fn ($p) => $p->has('calendar.items', 0));
});

it('JDW-03 tanggal dan tampilan kalender divalidasi', function () {
    $s = siapJadwal();

    test()->actingAs($s['user'])->get('/schedule?date=bukan-tanggal')->assertSessionHasErrors('date');
    test()->actingAs($s['user'])->get('/schedule?view=bulan')->assertSessionHasErrors('view');
});

it('JDW-02 slot kosong menghormati jadwal praktik, janji yang ada, dan waktu sekarang', function () {
    $s = siapJadwal();
    bukaJanji($s, ['time' => '10:00']);
    majuKe('2025-06-16 09:10:00');

    $slot = test()->actingAs($s['user'])->getJson('/internal-api/appointments/availability?doctor='.$s['dokter']->id.'&date='.SENIN.'&minutes=30')
        ->assertOk()->json('data');

    expect($slot)->not->toContain('09:00', '09:15', '10:00', '10:15', '08:00')
        ->and($slot)->toContain('09:30', '10:30', '14:00', '16:30')
        ->and($slot)->not->toContain('16:45');
});

it('JDW-02 slot kosong kosong pada hari libur dan hari tanpa praktik', function () {
    $s = siapJadwal();
    tenant()->set($s['klinik']->id);
    (new HolidayModel)->forceFill(['date' => SELASA, 'name' => 'Libur uji'])->save();

    test()->actingAs($s['user'])->getJson('/internal-api/appointments/availability?doctor='.$s['dokter']->id.'&date='.SELASA)->assertOk()->assertJsonPath('data', []);
    test()->actingAs($s['user'])->getJson('/internal-api/appointments/availability?doctor='.$s['dokter']->id.'&date=2025-06-18')->assertOk()->assertJsonPath('data', []);
});

it('JDW-02 slot kosong memperhitungkan kursi yang dipilih', function () {
    $s = siapJadwal();
    bukaJanji($s, ['time' => '09:00']);
    $dokter2 = makeDoctor($s['klinik'], 'drg. Budi', [['weekday' => 1, 'start' => '09:00', 'end' => '10:00']]);

    $tanpaKursi = test()->actingAs($s['user'])->getJson('/internal-api/appointments/availability?doctor='.$dokter2->id.'&date='.SENIN.'&minutes=30')->json('data');
    $denganKursi = test()->actingAs($s['user'])->getJson('/internal-api/appointments/availability?doctor='.$dokter2->id.'&date='.SENIN.'&minutes=30&chair='.$s['kursi']->id)->json('data');

    expect($tanpaKursi)->toContain('09:00')->and($denganKursi)->not->toContain('09:00')->and($denganKursi)->toContain('09:30');
});

it('JDW izin: dokter hanya melihat; resepsionis dapat mengubah; peran tanpa izin ditolak', function () {
    $s = siapJadwal();
    $id = bukaJanji($s);
    $dokter = makeUser(Role::Dokter, $s['klinik']);
    $pemilik = makeUser(Role::Pemilik, $s['klinik']);

    test()->actingAs($dokter)->get('/schedule')->assertOk()->assertInertia(fn ($p) => $p->where('canManage', false));
    test()->actingAs($dokter)->post('/appointments', janji($s, ['time' => '10:00']))->assertForbidden();
    test()->actingAs($dokter)->post("/appointments/{$id}/check-in")->assertForbidden();
    test()->actingAs($pemilik)->get('/queue')->assertOk();
    test()->actingAs($pemilik)->post('/walk-ins', ['patient_id' => $s['pasien'], 'doctor_id' => $s['dokter']->id])->assertForbidden();
});

it('JDW janji klinik lain tidak dapat diubah', function () {
    $a = siapJadwal();
    $id = bukaJanji($a);
    $b = siapJadwal();

    test()->actingAs($b['user'])->post("/appointments/{$id}/confirm")->assertNotFound();
    test()->actingAs($b['user'])->post("/appointments/{$id}/cancel", ['reason' => 'x'])->assertNotFound();
    test()->actingAs($b['user'])->put("/appointments/{$id}", janji($b, ['reason' => 'x']))->assertNotFound();
});

it('JDW-04 check-in memicu event PatientCheckedIn tanpa data pasien', function () {
    Event::fake([PatientCheckedIn::class]);
    $s = siapJadwal();
    $id = bukaJanji($s);

    test()->actingAs($s['user'])->post("/appointments/{$id}/check-in");

    Event::assertDispatched(PatientCheckedIn::class, fn ($e) => $e->appointmentId === $id && $e->patientId === $s['pasien'] && $e->doctorId === $s['dokter']->id);
});

it('JDW-05 formulir pasien tanpa janji adalah halaman sendiri dan simpan kembali ke antrean', function () {
    $s = siapJadwal();

    test()->actingAs($s['user'])->get('/queue/walk-in')->assertOk()->assertInertia(fn ($p) => $p
        ->component('Queue/WalkIn')->has('doctors', 1)->has('chairs', 1));
    test()->actingAs($s['user'])->post('/walk-ins', ['patient_id' => $s['pasien'], 'doctor_id' => $s['dokter']->id])->assertRedirect('/queue');
    test()->actingAs(makeUser(Role::Dokter, $s['klinik']))->get('/queue/walk-in')->assertForbidden();
});

it('JDW-01 buat janji, jadwal ulang, dan batal adalah halaman sendiri dan kembali ke kalender pada tanggal janji', function () {
    $s = siapJadwal();
    majuKe(SENIN.' 08:00');

    test()->actingAs($s['user'])->get('/appointments/create?date='.SENIN)->assertOk()->assertInertia(fn ($p) => $p
        ->component('Schedule/Create')->where('defaultDate', SENIN)->has('doctors', 1));

    $id = bukaJanji($s);
    test()->actingAs($s['user'])->get("/appointments/{$id}/edit")->assertOk()->assertInertia(fn ($p) => $p
        ->component('Schedule/Reschedule')->where('item.id', $id)->where('item.date', SENIN));
    test()->actingAs($s['user'])->get("/appointments/{$id}/cancel")->assertOk()->assertInertia(fn ($p) => $p
        ->component('Schedule/Cancel')->where('item.id', $id));

    test()->actingAs($s['user'])->put("/appointments/{$id}", ['doctor_id' => $s['dokter']->id, 'chair_id' => $s['kursi']->id, 'date' => SENIN, 'time' => '10:00', 'minutes' => 30, 'reason' => 'Pasien minta mundur'])
        ->assertRedirect('/schedule?date='.SENIN);
    test()->actingAs($s['user'])->post("/appointments/{$id}/cancel", ['reason' => 'Pasien berhalangan'])->assertRedirect('/schedule?date='.SENIN);
});

it('JDW-01 halaman janji milik klinik lain tidak dapat dibuka dan dokter tidak boleh membuat janji', function () {
    $a = siapJadwal();
    $b = siapJadwal();
    majuKe(SENIN.' 08:00');
    $id = bukaJanji($a);

    test()->actingAs($b['user'])->get("/appointments/{$id}/edit")->assertNotFound();
    test()->actingAs($b['user'])->get("/appointments/{$id}/cancel")->assertNotFound();
    test()->actingAs(makeUser(Role::Dokter, $a['klinik']))->get('/appointments/create')->assertForbidden();
});
