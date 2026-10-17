<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/** @return array{klinik: object, pemilik: object, admin: object} */
function siapAudit(): array
{
    $klinik = makeClinic();
    tenant()->set($klinik->id);

    return ['klinik' => $klinik, 'pemilik' => makeUser(Role::Pemilik, $klinik), 'admin' => makeUser(Role::Admin, $klinik)];
}

function catatAudit(string $aksi, ?string $pelaku, string $jenis = 'patient', string $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV', ?array $sebelum = null, ?array $sesudah = null): void
{
    app(AuditLogger::class)->record(new AuditEntry($pelaku, $aksi, $jenis, $id, $sebelum, $sesudah));
}

it('SYS-01 Pemilik dan Admin melihat audit log; peran lain ditolak', function () {
    $s = siapAudit();
    catatAudit('patient.updated', $s['admin']->id);

    foreach (['pemilik', 'admin'] as $peran) {
        $this->actingAs($s[$peran])->get('/audit')->assertOk()->assertInertia(fn ($p) => $p->component('Audit/Index')->where('total', 1)
            ->where('rows.0.action', 'patient.updated')->where('rows.0.actor_name', $s['admin']->name));
    }
    foreach ([Role::Kasir, Role::Dokter, Role::Resepsionis, Role::Perawat] as $role) {
        $this->actingAs(makeUser($role, $s['klinik']))->get('/audit')->assertForbidden();
        $this->actingAs(makeUser($role, $s['klinik']))->get('/audit/access')->assertForbidden();
    }
});

it('SYS-01 audit hanya-baca: tidak ada route ubah atau hapus di bawah /audit', function () {
    foreach (app('router')->getRoutes() as $route) {
        if (str_starts_with($route->uri(), 'audit')) {
            expect($route->methods())->toBe(['GET', 'HEAD']);
        }
    }
});

it('SYS-01 penyaring aksi, jenis subjek, pelaku, dan tanggal', function () {
    $s = siapAudit();
    catatAudit('invoice.discount_applied', $s['admin']->id, 'invoice', '01ARZ3NDEKTSV4RRFFQ69G5FA1');
    catatAudit('invoice.voided', $s['pemilik']->id, 'invoice', '01ARZ3NDEKTSV4RRFFQ69G5FA1');
    catatAudit('patient.updated', $s['admin']->id, 'patient', '01ARZ3NDEKTSV4RRFFQ69G5FA2');
    Carbon::setTestNow(Carbon::parse('2025-06-20 07:00:00', 'Asia/Jakarta'));
    catatAudit('patient.created', $s['admin']->id, 'patient', '01ARZ3NDEKTSV4RRFFQ69G5FA3');

    $total = fn (string $query) => $this->actingAs($s['pemilik'])->get('/audit'.$query)->inertiaProps('total');
    expect($total(''))->toBe(4)
        ->and($total('?action=invoice.'))->toBe(2)
        ->and($total('?subject_type=patient'))->toBe(2)
        ->and($total('?subject_id=01ARZ3NDEKTSV4RRFFQ69G5FA1'))->toBe(2)
        ->and($total('?actor_id='.$s['pemilik']->id))->toBe(1)
        ->and($total('?from=2025-06-20'))->toBe(1)
        ->and($total('?to=2025-06-16'))->toBe(3)
        ->and($total('?from=2025-06-17&to=2025-06-19'))->toBe(0);

    $this->actingAs($s['pemilik'])->get('/audit?from=bukan-tanggal')->assertSessionHasErrors('from');
});

it('SYS-01 daftar terbaru lebih dulu dan terbagi halaman', function () {
    $s = siapAudit();
    for ($i = 1; $i <= 55; $i++) {
        Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta')->addMinutes($i));
        catatAudit('x.created', $s['admin']->id, 'x', sprintf('X%025d', $i));
    }

    $this->actingAs($s['pemilik'])->get('/audit')->assertInertia(fn ($p) => $p->where('total', 55)->where('per_page', 50)->where('rows.0.subject_id', sprintf('X%025d', 55))->has('rows', 50));
    $this->actingAs($s['pemilik'])->get('/audit?page=2')->assertInertia(fn ($p) => $p->has('rows', 5)->where('rows.4.subject_id', sprintf('X%025d', 1)));
});

it('SYS-01 detail menampilkan nilai sebelum dan sesudah dan pembukaannya tercatat', function () {
    $s = siapAudit();
    catatAudit('patient.updated', $s['admin']->id, 'patient', '01ARZ3NDEKTSV4RRFFQ69G5FAV', ['name' => 'Lama', 'phone' => '0811', 'aktif' => true], ['name' => 'Baru', 'phone' => '0811', 'aktif' => false, 'catatan' => ['a' => 1]]);
    $id = AuditLogModel::query()->firstOrFail()->id;

    $this->actingAs($s['pemilik'])->get("/audit/{$id}")->assertOk()->assertInertia(fn ($p) => $p->component('Audit/Show')
        ->where('entry.actor_name', $s['admin']->name)->where('entry.action', 'patient.updated'));

    // Urutan kunci JSON di MySQL tidak dijamin; cocokkan per nama kolom.
    $kolom = collect($this->actingAs($s['pemilik'])->get("/audit/{$id}")->inertiaProps('entry.fields'))->keyBy('field');
    expect($kolom['name'])->toMatchArray(['before' => 'Lama', 'after' => 'Baru', 'changed' => true])
        ->and($kolom['phone']['changed'])->toBeFalse()
        ->and($kolom['aktif'])->toMatchArray(['before' => 'ya', 'after' => 'tidak', 'changed' => true])
        ->and($kolom['catatan'])->toMatchArray(['before' => null, 'after' => '{"a":1}', 'changed' => true]);

    tenant()->set($s['klinik']->id);
    $dilihat = AuditLogModel::query()->where('action', 'audit.viewed')->firstOrFail();
    expect($dilihat->actor_id)->toBe($s['pemilik']->id)->and($dilihat->subject_id)->toBe($id);
});

it('SYS-01 detail log klinik lain tidak ditemukan', function () {
    $a = siapAudit();
    catatAudit('patient.updated', $a['admin']->id);
    $id = AuditLogModel::query()->firstOrFail()->id;

    $b = siapAudit();
    $this->actingAs($b['pemilik'])->get("/audit/{$id}")->assertNotFound();
    $this->actingAs($b['pemilik'])->get('/audit')->assertInertia(fn ($p) => $p->where('total', 0));
});

it('SYS-01 jejak akses menampilkan siapa membuka rekam siapa dengan nama pasien dan nomor RM', function () {
    $s = siapAudit();
    $pasien = makePatient($s['klinik'], 'Budi Santoso');
    $dokter = makeUser(Role::Dokter, $s['klinik']);
    DB::table('access_logs')->insert(['id' => strtoupper((string) Str::ulid()), 'clinic_id' => $s['klinik']->id, 'actor_id' => $dokter->id, 'patient_id' => $pasien, 'route' => 'patients.show', 'ip' => '10.0.0.5', 'at' => now()]);

    $this->actingAs($s['pemilik'])->get('/audit/access')->assertOk()->assertInertia(fn ($p) => $p->component('Audit/Accesses')->where('total', 1)
        ->where('rows.0.actor_name', $dokter->name)->where('rows.0.patient_name', 'Budi Santoso')->where('rows.0.route', 'patients.show')->where('rows.0.ip', '10.0.0.5'));
    $this->actingAs($s['pemilik'])->get('/audit/access?actor_id='.$s['admin']->id)->assertInertia(fn ($p) => $p->where('total', 0));
});

it('SYS-01 membuka rekam pasien lewat aplikasi tercatat dan dapat dilihat Pemilik', function () {
    $s = siapAudit();
    $pasien = makePatient($s['klinik'], 'Siti Rahmawati');
    $resepsionis = makeUser(Role::Resepsionis, $s['klinik']);

    $this->actingAs($resepsionis)->get("/patients/{$pasien}")->assertOk();

    tenant()->set($s['klinik']->id);
    $this->actingAs($s['pemilik'])->get('/audit/access')->assertInertia(fn ($p) => $p->where('total', 1)->where('rows.0.actor_name', $resepsionis->name)->where('rows.0.patient_name', 'Siti Rahmawati'));
});

it('SYS-01 setiap konteks yang mengubah data sensitif memanggil AuditLogger', function () {
    foreach (['Patient', 'Clinical', 'Billing', 'IdentityAccess', 'Pharmacy', 'Clinic'] as $konteks) {
        $kode = '';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path("src/{$konteks}"))) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $kode .= file_get_contents($file->getPathname());
            }
        }
        expect(substr_count($kode, 'new AuditEntry('))->toBeGreaterThan(0, "Konteks {$konteks} tidak mencatat audit.");
    }
});
