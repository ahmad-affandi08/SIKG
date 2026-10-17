<?php

// TEMPLAT: salin ke tests/Feature/Clinical/ClosedEncounterTest.php
// Memakai Clock palsu untuk memajukan waktu; jangan memakai sleep.

declare(strict_types=1);

it('RM-07 kunjungan tertutup tidak dapat diubah langsung setelah 2x24 jam', function () {
    $dokter = doctorUser();
    $enc = createClosedEncounter(['doctor_id' => $dokter->doctorId()]);
    fakeClock()->advanceHours(49);

    $this->actingAs($dokter)->put("/encounters/{$enc->id}/notes", ['assessment' => 'ubah'])
        ->assertStatus(409); // kode bisnis: encounter.locked
});

it('RM-07 perbaikan oleh pengguna berwenang diterima dalam 2x24 jam', function () {
    $dokter = doctorUser();
    $enc = createClosedEncounter(['doctor_id' => $dokter->doctorId()]);
    fakeClock()->advanceHours(47);

    $this->actingAs($dokter)->put("/encounters/{$enc->id}/notes", ['assessment' => 'koreksi'])->assertRedirect();
});

it('RM-07 setelah 2x24 jam hanya addendum bertanda waktu dan nama pengguna', function () {
    $dokter = doctorUser();
    $enc = createClosedEncounter(['doctor_id' => $dokter->doctorId()]);
    fakeClock()->advanceHours(72);

    $this->actingAs($dokter)->post("/encounters/{$enc->id}/addenda", ['body' => 'tambahan'])->assertRedirect();

    expect(noteTypes($enc))->toContain('addendum')
        ->and(originalNoteUnchanged($enc))->toBeTrue();
});

it('SYS-03 tidak ada route hapus permanen untuk catatan klinis', function () {
    expect(routeExists('DELETE', '/encounters/{encounter}'))->toBeFalse();
});
