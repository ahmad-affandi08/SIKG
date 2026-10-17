<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Infrastructure\Audit\AccessLogModel;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

it('SYS-01 audit mencatat aktor, aksi, subjek, sebelum, sesudah, IP, dan waktu', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    tenant()->set($klinik->id);

    app(AuditLogger::class)->record(new AuditEntry(
        actorId: $admin->id,
        action: 'patient.updated',
        subjectType: 'patient',
        subjectId: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        before: ['name' => 'Lama'],
        after: ['name' => 'Baru'],
    ));

    $log = AuditLogModel::query()->firstOrFail();
    expect($log->actor_id)->toBe($admin->id)
        ->and($log->action)->toBe('patient.updated')
        ->and($log->subject_type)->toBe('patient')
        ->and($log->before)->toBe(['name' => 'Lama'])
        ->and($log->after)->toBe(['name' => 'Baru'])
        ->and($log->getAttribute('clinic_id'))->toBe($klinik->id)
        ->and($log->created_at)->not->toBeNull()
        ->and(strlen($log->id))->toBe(26);
});

it('M10 audit_logs tidak dapat diubah lewat model', function () {
    $klinik = makeClinic();
    tenant()->set($klinik->id);
    app(AuditLogger::class)->record(new AuditEntry(null, 'x.created', 'x', 'X1'));
    $log = AuditLogModel::query()->firstOrFail();

    $log->action = 'diubah';
    $log->save();
})->throws(LogicException::class, 'Log audit tidak dapat diubah.');

it('M10 audit_logs tidak dapat dihapus lewat model', function () {
    $klinik = makeClinic();
    tenant()->set($klinik->id);
    app(AuditLogger::class)->record(new AuditEntry(null, 'x.created', 'x', 'X1'));

    AuditLogModel::query()->firstOrFail()->delete();
})->throws(LogicException::class, 'Log audit tidak dapat dihapus.');

it('M10 ubah dan hapus massal pada log juga ditolak', function (string $operasi) {
    $klinik = makeClinic();
    tenant()->set($klinik->id);
    app(AuditLogger::class)->record(new AuditEntry(null, 'x.created', 'x', 'X1'));

    match ($operasi) {
        'update' => AuditLogModel::query()->update(['action' => 'diubah']),
        'delete' => AuditLogModel::query()->delete(),
        'access-delete' => AccessLogModel::query()->delete(),
    };
})->with(['update', 'delete', 'access-delete'])->throws(LogicException::class);

it('M10 baris log tetap utuh setelah percobaan ubah dan hapus', function () {
    $klinik = makeClinic();
    tenant()->set($klinik->id);
    app(AuditLogger::class)->record(new AuditEntry(null, 'x.created', 'x', 'X1'));

    try {
        AuditLogModel::query()->delete();
    } catch (LogicException) {
    }

    expect(DB::table('audit_logs')->where('action', 'x.created')->count())->toBe(1);
});

it('M10 tidak ada route yang mengubah atau menghapus audit log', function () {
    $melanggar = [];
    foreach (app('router')->getRoutes() as $route) {
        $uri = $route->uri();
        if (str_contains($uri, 'audit') || str_contains($uri, 'access-log')) {
            foreach (array_intersect($route->methods(), ['PUT', 'PATCH', 'DELETE']) as $metode) {
                $melanggar[] = "{$metode} {$uri}";
            }
        }
    }

    expect($melanggar)->toBe([]);
});

it('M10 pembukaan rekam medis pasien menulis access_logs', function () {
    $klinik = makeClinic();
    $dokter = makeUser(Role::Dokter, $klinik);
    tenant()->set($klinik->id);
    $this->actingAs($dokter);

    app(AccessLogger::class)->patientRecordOpened('01ARZ3NDEKTSV4RRFFQ69G5FAV', 'encounters.show');

    $log = AccessLogModel::query()->firstOrFail();
    expect($log->actor_id)->toBe($dokter->id)
        ->and($log->patient_id)->toBe('01ARZ3NDEKTSV4RRFFQ69G5FAV')
        ->and($log->route)->toBe('encounters.show');
});

it('M10 jejak audit klinik lain tidak terlihat', function () {
    $a = makeClinic();
    $b = makeClinic();
    tenant()->set($a->id);
    app(AuditLogger::class)->record(new AuditEntry(null, 'a.created', 'x', 'X1'));
    tenant()->set($b->id);
    app(AuditLogger::class)->record(new AuditEntry(null, 'b.created', 'x', 'X2'));

    tenant()->set($a->id);
    expect(AuditLogModel::query()->pluck('action')->all())->toBe(['a.created']);
});
