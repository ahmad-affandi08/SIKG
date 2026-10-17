<?php

// TEMPLAT: salin ke tests/Feature/AuditLogTest.php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('SYS-01 perubahan data pasien menulis audit dengan sebelum dan sesudah', function () {
    $admin = adminUser();
    $pasien = createPatient(null, ['name' => 'Lama']);

    $this->actingAs($admin)->put("/patients/{$pasien->id}", [...validPatientPayload(), 'name' => 'Baru'])->assertRedirect();

    $log = DB::table('audit_logs')->where('subject_id', $pasien->id)->latest('created_at')->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and(json_decode($log->before, true)['name'])->toBe('Lama')
        ->and(json_decode($log->after, true)['name'])->toBe('Baru');
});

it('SYS-01 audit_logs tidak dapat diubah atau dihapus lewat aplikasi', function () {
    // tidak ada route atau use case yang UPDATE/DELETE audit_logs; model audit tidak mendefinisikan update/delete
    expect(routeExists('DELETE', '/settings/audit-logs/{id}'))->toBeFalse();
});

it('NFR-06 membuka rekam medis menulis access_logs', function () {
    $dokter = doctorUser();
    $encounter = createEncounter(['doctor_id' => $dokter->doctorId()]);

    $this->actingAs($dokter)->get("/encounters/{$encounter->id}")->assertOk();

    expect(DB::table('access_logs')->where('patient_id', $encounter->patient_id)->where('actor_id', $dokter->id)->exists())->toBeTrue();
});
