<?php

declare(strict_types=1);

use App\Http\Middleware\DedupeOfflineReplay;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\EnforcePasswordChange;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireDentalProfile;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Src\Shared\Domain\DomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ResolveTenant::class,
            EnforceIdleTimeout::class,
            EnforcePasswordChange::class,
            HandleInertiaRequests::class,
        ]);
        // P3-02: pengiriman ulang antrean offline hanya dijalankan sekali per kunci (dipasang di route anamnesis dan catatan).
        $middleware->alias(['offline.dedupe' => DedupeOfflineReplay::class, 'profile.dental' => RequireDentalProfile::class]);
        // Global (bukan grup web) agar header juga ada di 404, /up, dan pengalihan.
        $middleware->append(SecurityHeaders::class);
        // Proxy tepercaya dibaca dari config/trustedproxy.php (bawaan: proxy lokal/privat saja). Jangan percaya semua alamat:
        // header X-Forwarded-For dari klien akan dipakai apa adanya dan melewati pembatasan laju.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('internal-api/*') || $request->expectsJson(),
        );

        // Galat aturan bisnis: kode mesin stabil, status 409 atau 422 (docs/rules/backend-ddd.md bagian 4).
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'code' => $e->businessCode()], $e->httpStatus());
            }

            // Penolakan izin tetap 403 dan data yang tidak ada tetap 404; aturan bisnis lain kembali ke halaman dengan pesan galat.
            if (in_array($e->httpStatus(), [403, 404], true)) {
                abort($e->httpStatus(), $e->getMessage());
            }

            return back()->withErrors(['domain' => $e->getMessage()])->with('error', $e->getMessage())->with('error_code', $e->businessCode());
        });
    })->create();
