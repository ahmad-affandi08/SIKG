<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\HandleInertiaRequests;
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
            HandleInertiaRequests::class,
        ]);
        // Global (bukan grup web) agar header juga ada di 404, /up, dan pengalihan.
        $middleware->append(SecurityHeaders::class);
        // Percaya proxy hosting (HTTPS diakhiri di penyedia) agar cookie aman dan HSTS benar.
        $middleware->trustProxies(at: '*');
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

            // Penolakan izin tetap 403; aturan bisnis lain kembali ke halaman dengan pesan galat.
            if ($e->httpStatus() === 403) {
                abort(403, $e->getMessage());
            }

            return back()->withErrors(['domain' => $e->getMessage()])->with('error_code', $e->businessCode());
        });
    })->create();
