<?php

declare(strict_types=1);

use Illuminate\Routing\Route;

/*
 * M9 dan bawaan aman: setiap route di luar daftar publik di bawah HARUS berada di balik login
 * (middleware auth) DAN pemeriksaan izin (middleware can:...). Route modul baru yang lupa izin
 * membuat tes ini gagal, sehingga celah otorisasi tidak lolos diam-diam.
 */

/** Route publik: uri => metode yang diizinkan ('*' = semua, mis. pengalihan). */
const ROUTE_PUBLIK = [
    '/' => '*',
    '/login' => ['GET', 'POST'],
    '/login/two-factor' => ['GET', 'POST'], // AUTH-05: hanya berlaku dengan ID pengguna tertunda di sesi setelah kata sandi benar
    '/up' => ['GET'],
    '/health' => ['GET'], // dilindungi token X-Health-Token, bukan sesi (SYS-07)
    // P3-01: booking online oleh pasien. Klinik ditentukan dari {slug} (404 bila booking mati), semua dibatasi throttle, dan tidak ada
    // data pasien yang dapat dibaca: status hanya lewat token acak 40 heksa milik pembuat permintaan.
    '/book/{slug}' => ['GET', 'POST'],
    '/internal-api/book/{slug}/slots' => ['GET'],
    '/book/{slug}/r/{token}' => ['GET'],
    '/book/{slug}/r/{token}/verify' => ['POST'],
    '/book/{slug}/r/{token}/resend' => ['POST'],
    '/book/{slug}/r/{token}/cancel' => ['POST'],
];

/** Route khusus pengguna yang masuk tanpa izin spesifik (tidak memuat data sensitif): uri => metode. */
const ROUTE_LOGIN_SAJA = [
    '/dashboard' => ['GET'],
    '/logout' => ['POST'],
];

/**
 * @return list<array{string, string, Route}> [metode, uri, route]
 */
function semuaRoute(): array
{
    $hasil = [];
    foreach (app('router')->getRoutes() as $route) {
        foreach (array_diff($route->methods(), ['HEAD']) as $metode) {
            $hasil[] = [$metode, '/'.ltrim($route->uri(), '/'), $route];
        }
    }

    return $hasil;
}

function diizinkan(array $daftar, string $metode, string $uri): bool
{
    if (! isset($daftar[$uri])) {
        return false;
    }

    return $daftar[$uri] === '*' || in_array($metode, $daftar[$uri], true);
}

it('AUTH-02 setiap route non-publik memakai auth dan pemeriksaan izin', function () {
    $melanggar = [];
    foreach (semuaRoute() as [$metode, $uri, $route]) {
        if (diizinkan(ROUTE_PUBLIK, $metode, $uri)) {
            continue;
        }

        $middleware = $route->gatherMiddleware();
        $adaAuth = in_array('auth', $middleware, true);
        $adaIzin = array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'can:')) !== [];

        if (diizinkan(ROUTE_LOGIN_SAJA, $metode, $uri)) {
            if (! $adaAuth) {
                $melanggar[] = "{$metode} {$uri} tanpa auth";
            }

            continue;
        }

        if (! $adaAuth || ! $adaIzin) {
            $melanggar[] = "{$metode} {$uri} (auth=".($adaAuth ? 'ya' : 'tidak').', izin='.($adaIzin ? 'ya' : 'tidak').')';
        }
    }

    expect($melanggar)->toBe([], 'Route tanpa auth dan izin: '.implode('; ', $melanggar));
});

it('AUTH-02 daftar pengecualian tidak berisi route yang sudah tidak ada', function () {
    $uris = array_map(fn ($r) => $r[1], semuaRoute());

    foreach (array_merge(array_keys(ROUTE_PUBLIK), array_keys(ROUTE_LOGIN_SAJA)) as $uri) {
        expect($uris)->toContain($uri);
    }
});
