<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;

function pasangLogo(ClinicModel $klinik): void
{
    $path = "clinics/{$klinik->id}/logo-uji.png";
    Storage::disk('private')->put($path, 'png-sintetis');
    $klinik->forceFill(['logo_path' => $path])->save();
}

it('AUTH-01 halaman masuk menampilkan nama dan logo klinik tunggal tanpa login', function () {
    Storage::fake('private');
    $klinik = makeClinic();
    pasangLogo($klinik);

    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page
        ->where('brand.name', $klinik->name)
        ->where('brand.logoUrl', fn ($url) => str_starts_with((string) $url, '/brand/logo?v=')));

    $this->get('/brand/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('AUTH-01 klinik tanpa logo menampilkan nama saja dan logo publik 404', function () {
    Storage::fake('private');
    $klinik = makeClinic();

    $this->get('/login')->assertInertia(fn ($page) => $page->where('brand.name', $klinik->name)->where('brand.logoUrl', null));
    $this->get('/brand/logo')->assertNotFound();
});

it('AUTH-01 dengan lebih dari satu klinik halaman masuk netral dan logo tidak dibuka', function () {
    Storage::fake('private');
    $pertama = makeClinic();
    pasangLogo($pertama);
    makeClinic();

    $this->get('/login')->assertInertia(fn ($page) => $page->where('brand', null));
    $this->get('/brand/logo')->assertNotFound();
});

it('AUTH-01 tanpa klinik sama sekali halaman masuk tetap terbuka dan netral', function () {
    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->where('brand', null));
});
