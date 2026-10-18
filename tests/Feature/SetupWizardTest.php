<?php

declare(strict_types=1);

use Database\Seeders\StarterDataSeeder;
use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Domain\Role;

it('MST-10 klinik baru belum memenuhi langkah wajib dan halaman menampilkan daftar periksa', function () {
    $pemilik = makeUser(Role::Pemilik);

    $this->actingAs($pemilik)->get('/settings/setup')->assertOk()->assertInertia(fn ($halaman) => $halaman
        ->component('Settings/Setup')
        ->where('required_total', 5)
        ->where('required_done', 0)
        ->has('steps', 8)
        ->where('steps.0.key', 'profile')
        ->where('steps.4.import_href', '/settings/master/procedures/import'));
});

it('MST-10 kemajuan mengikuti isi data klinik, bukan status tersimpan', function () {
    $klinik = makeClinic();
    $pemilik = makeUser(Role::Pemilik, $klinik);
    DB::table('clinics')->where('id', $klinik->id)->update(['address' => 'Jl. Contoh 1', 'license_no' => 'SIP-001']);
    makeDoctor($klinik, 'drg. Contoh');
    makeChair($klinik, 'Kursi 1');
    $this->seed(StarterDataSeeder::class);
    tenant()->set($klinik->id);
    tindakan(['klinik' => $klinik], ['code' => 'ADA-HARGA', 'default_price_amount' => 150000]);

    $this->actingAs($pemilik)->get('/settings/setup')->assertInertia(fn ($halaman) => $halaman
        ->where('required_done', 5)
        ->where('steps.0.done', true)
        ->where('steps.1.done', true)
        ->where('steps.4.done', true)
        // Tindakan data awal nonaktif dan berharga 0, jadi belum dihitung sampai klinik mengisinya.
        ->where('steps.5.done', false)
        ->where('steps.7.done', false));
});

it('MST-10 tombol data awal memuat data awal untuk klinik pemanggil saja, tercatat di audit, dan aman diulang', function () {
    $klinikA = makeClinic('Klinik A');
    $klinikB = makeClinic('Klinik B');
    $pemilik = makeUser(Role::Pemilik, $klinikA);

    $this->actingAs($pemilik)->post('/settings/setup/starter-data')->assertSessionHas('success');

    expect(DB::table('procedures')->where('clinic_id', $klinikA->id)->count())->toBeGreaterThan(0)
        ->and(DB::table('procedures')->where('clinic_id', $klinikB->id)->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('action', 'setup.starter_data_loaded')->count())->toBe(1);

    $jumlah = DB::table('procedures')->where('clinic_id', $klinikA->id)->count();
    $this->actingAs($pemilik)->post('/settings/setup/starter-data')
        ->assertSessionHas('success', 'Data awal sudah lengkap, tidak ada yang ditambahkan.');
    expect(DB::table('procedures')->where('clinic_id', $klinikA->id)->count())->toBe($jumlah);
});

it('MST-10 hanya pemegang izin pengaturan yang dapat membuka halaman dan memuat data awal', function () {
    $klinik = makeClinic();
    $kasir = makeUser(Role::Kasir, $klinik);

    $this->actingAs($kasir)->get('/settings/setup')->assertForbidden();
    $this->actingAs($kasir)->post('/settings/setup/starter-data')->assertForbidden();

    expect(DB::table('procedures')->count())->toBe(0);
});
