<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

function payloadProfil(array $override = []): array
{
    return [
        'name' => 'Klinik Gigi Senyum',
        'address' => 'Jl. Merdeka No. 1',
        'city' => 'Surakarta',
        'license_no' => 'IZIN/123/2025',
        'receipt_header' => "Klinik Gigi Senyum\nJl. Merdeka No. 1",
        'prescription_header' => 'Klinik Gigi Senyum - Resep',
        ...$override,
    ];
}

it('MST-01 Admin menyimpan profil klinik dan perubahan tercatat di audit', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->put('/settings/profile', payloadProfil())->assertSessionHasNoErrors()->assertRedirect();

    $klinik->refresh();
    expect($klinik->name)->toBe('Klinik Gigi Senyum')
        ->and($klinik->city)->toBe('Surakarta')
        ->and($klinik->license_no)->toBe('IZIN/123/2025')
        ->and($klinik->receipt_header)->toContain('Jl. Merdeka');
    tenant()->set($klinik->id);
    $log = AuditLogModel::query()->where('action', 'clinic.profile_updated')->firstOrFail();
    expect($log->before['name'])->toBe('Klinik Uji')->and($log->after['name'])->toBe('Klinik Gigi Senyum');
});

it('MST-01 nama klinik wajib dan kolom kosong menjadi null', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->put('/settings/profile', payloadProfil(['name' => '']))->assertSessionHasErrors('name');
    $this->actingAs($admin)->put('/settings/profile', payloadProfil(['address' => '  ', 'license_no' => '']))->assertSessionHasNoErrors();

    expect($klinik->refresh()->address)->toBeNull()->and($klinik->license_no)->toBeNull();
});

it('MST-01 logo PNG tersimpan di disk privat dan dilayani lewat route berizin', function () {
    Storage::fake('private');
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->post('/settings/profile/logo', ['logo' => UploadedFile::fake()->image('logo.png', 100, 100)])
        ->assertSessionHasNoErrors();

    $path = $klinik->refresh()->logo_path;
    expect($path)->toStartWith("clinics/{$klinik->id}/logo-")->and($path)->toEndWith('.png');
    Storage::disk('private')->assertExists($path);
    $this->actingAs($admin)->get('/settings/profile/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('MST-01 mengganti logo menghapus berkas lama', function () {
    Storage::fake('private');
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->post('/settings/profile/logo', ['logo' => UploadedFile::fake()->image('a.png')]);
    $lama = $klinik->refresh()->logo_path;
    $this->actingAs($admin)->post('/settings/profile/logo', ['logo' => UploadedFile::fake()->image('b.jpg')]);

    Storage::disk('private')->assertMissing($lama);
    Storage::disk('private')->assertExists($klinik->refresh()->logo_path);
});

it('MST-01 logo menolak SVG, berkas bukan gambar, dan ukuran lebih dari 1 MB', function () {
    Storage::fake('private');
    $admin = makeUser(Role::Admin);

    $this->actingAs($admin)->post('/settings/profile/logo', ['logo' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
        ->assertSessionHasErrors('logo');
    $this->actingAs($admin)->post('/settings/profile/logo', ['logo' => UploadedFile::fake()->create('x.png', 10, 'application/pdf')])
        ->assertSessionHasErrors('logo');
    $this->actingAs($admin)->post('/settings/profile/logo', ['logo' => UploadedFile::fake()->image('besar.png')->size(2048)])
        ->assertSessionHasErrors('logo');
});

it('MST-01 logo dihapus dan route logo menjawab 404 sesudahnya', function () {
    Storage::fake('private');
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $this->actingAs($admin)->post('/settings/profile/logo', ['logo' => UploadedFile::fake()->image('a.png')]);
    $path = $klinik->refresh()->logo_path;

    $this->actingAs($admin)->delete('/settings/profile/logo')->assertRedirect();

    Storage::disk('private')->assertMissing($path);
    expect($klinik->refresh()->logo_path)->toBeNull();
    $this->actingAs($admin)->get('/settings/profile/logo')->assertNotFound();
});

it('MST-01 logo klinik lain tidak terlihat dan peran tanpa izin ditolak', function () {
    Storage::fake('private');
    $a = makeClinic('A');
    $b = makeClinic('B');
    $this->actingAs(makeUser(Role::Admin, $a))->post('/settings/profile/logo', ['logo' => UploadedFile::fake()->image('a.png')]);

    $this->actingAs(makeUser(Role::Admin, $b))->get('/settings/profile/logo')->assertNotFound();
    $this->actingAs(makeUser(Role::Kasir, $a))->get('/settings/profile')->assertForbidden();
    $this->actingAs(makeUser(Role::Kasir, $a))->get('/settings/profile/logo')->assertForbidden();
    expect(ClinicModel::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('MST-01 halaman profil dirender dengan data klinik', function () {
    $this->actingAs(makeUser(Role::Admin))->get('/settings/profile')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/ClinicProfile')->where('profile.name', 'Klinik Uji')->where('profile.has_logo', false));
});
