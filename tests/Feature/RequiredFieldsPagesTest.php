<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Src\IdentityAccess\Domain\Role;

beforeEach(function () {
    majuKe(SENIN.' 08:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * UI-05: halaman formulir mengirim daftar isian wajib yang diturunkan dari aturan validasi yang sama dengan
 * yang menolak permintaan di server.
 */
it('UI-05 formulir pasien baru menandai nama, tanggal lahir, jenis kelamin, dan persetujuan sebagai wajib', function () {
    $admin = makeUser(Role::Admin, makeClinic());

    $this->actingAs($admin)->get('/patients/create')->assertOk()->assertInertia(function ($p) {
        $p->component('Patients/Form');
        $required = $p->toArray()['props']['required'];
        expect($required)->toContain('name', 'birth_date', 'sex', 'consent')->not->toContain('phone', 'nik', 'address');
    });
});

it('UI-05 formulir ubah pasien tidak mewajibkan persetujuan ulang', function () {
    $klinik = makeClinic();
    $id = makePatient($klinik, 'Pasien Wajib');

    $this->actingAs(makeUser(Role::Admin, $klinik))->get("/patients/{$id}/edit")->assertOk()->assertInertia(function ($p) {
        $required = $p->toArray()['props']['required'];
        expect($required)->toContain('name')->not->toContain('consent');
    });
});

it('UI-05 formulir pengguna, dokter, hari libur, riwayat medis, dan master data membawa daftar wajib', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $pasien = makePatient($klinik, 'Pasien Riwayat');

    $this->actingAs($admin)->get('/settings/users/create')->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toContain('name', 'email', 'username', 'role')->not->toContain('branch_id'));
    $this->actingAs($admin)->get('/settings/doctors/create')->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toContain('name', 'fee_mode', 'schedule.*.start', 'schedule.*.end')->not->toContain('sip_no'));
    $this->actingAs($admin)->get('/settings/holidays/create')->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toBe(['date', 'name']));
    $this->actingAs($admin)->get("/patients/{$pasien}/history/create")->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toBe(['type', 'name']));
    $this->actingAs($admin)->get('/settings/master/chairs/create')->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toContain('name'));
});

it('UI-05 pengaturan klinik mewajibkan jam buka dan tutup selama hari itu buka', function () {
    $this->actingAs(makeUser(Role::Admin, makeClinic()))->get('/settings/clinic')->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])
        ->toContain('timezone', 'medical_record_pattern', 'operating_hours.*.open', 'operating_hours.*.close'));
});

it('UI-05 janji baru mewajibkan pasien dan jadwal ulang mewajibkan alasan', function () {
    $s = siapJadwal();
    $id = bukaJanji($s);

    test()->actingAs($s['user'])->get('/appointments/create')->assertInertia(function ($p) {
        $required = $p->toArray()['props']['required'];
        expect($required)->toContain('patient_id', 'doctor_id', 'date', 'time', 'minutes')->not->toContain('reason', 'chair_id', 'complaint');
    });
    test()->actingAs($s['user'])->get("/appointments/{$id}/edit")->assertInertia(function ($p) {
        $required = $p->toArray()['props']['required'];
        expect($required)->toContain('reason', 'doctor_id')->not->toContain('patient_id');
    });
    test()->actingAs($s['user'])->get("/appointments/{$id}/cancel")->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toBe(['reason']));
    test()->actingAs($s['user'])->get('/queue/walk-in')->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toBe(['patient_id', 'doctor_id']));
});

it('UI-05 formulir stok, kunjungan, dan resep membawa daftar wajib', function () {
    $s = siapKunjungan();
    $k = $s['kunjungan'];

    $this->actingAs(makeUser(Role::Admin, $s['klinik']))->get('/stock/movements/create')->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toBe(['medicine_id', 'type', 'quantity']));
    $this->actingAs(makeUser(Role::Admin, $s['klinik']))->get('/stock/adjustments/create')->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toBe(['medicine_id', 'counted_quantity', 'reason']));
    $this->actingAs($s['dokter_user'])->get("/encounters/{$k}/diagnoses/create")->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toBe(['diagnosis_id']));
    $this->actingAs($s['dokter_user'])->get("/encounters/{$k}/procedures/create")->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toBe(['procedure_id', 'quantity']));
    $this->actingAs($s['dokter_user'])->get("/encounters/{$k}/files/create")->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toBe(['file', 'kind']));
    $this->actingAs($s['dokter_user'])->get("/encounters/{$k}/prescriptions/create")->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toContain('items', 'items.*.dose', 'items.*.frequency', 'items.*.duration', 'items.*.quantity')->not->toContain('items.*.instructions'));
    $this->actingAs($s['dokter_user'])->post("/encounters/{$k}/diagnoses", ['diagnosis_id' => icd()]);
    $this->actingAs($s['dokter_user'])->post("/encounters/{$k}/close");
    $this->actingAs($s['dokter_user'])->get("/encounters/{$k}/addenda/create")->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toBe(['body', 'reason']));
});

it('UI-05 halaman masuk menandai email atau nama pengguna dan kata sandi', function () {
    $this->get('/login')->assertOk()->assertInertia(fn ($p) => expect($p->toArray()['props']['required'])->toBe(['identifier', 'password']));
});
