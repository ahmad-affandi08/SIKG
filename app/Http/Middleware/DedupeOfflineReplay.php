<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Pengaman pengiriman ulang antrean offline (P3-02): permintaan yang membawa `X-Offline-Key` hanya dijalankan sekali per pengguna dan
 * alamat. Pengulangan dengan kunci yang sama (misalnya respons pertama hilang di jaringan) dijawab berhasil tanpa mencatat ganda.
 * Kunci dilepas bila permintaan gagal sehingga perbaikan dapat dikirim ulang dengan kunci itu.
 */
final class DedupeOfflineReplay
{
    private const TTL_DAYS = 7;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Offline-Key');
        if (! is_string($key) || preg_match('/^[0-9a-fA-F-]{36}$/', $key) !== 1) {
            return $next($request);
        }
        $user = $request->user()?->getAuthIdentifier();
        $cacheKey = 'offline-replay:'.hash('sha256', (is_string($user) ? $user : '').'|'.$request->method().'|'.$request->path().'|'.$key);

        if (! Cache::add($cacheKey, 1, now()->addDays(self::TTL_DAYS))) {
            return $request->expectsJson() ? response()->json(['ok' => true, 'duplicate' => true]) : back();
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            Cache::forget($cacheKey);

            throw $e;
        }
        if ($response->getStatusCode() >= 400) {
            Cache::forget($cacheKey);
        }

        return $response;
    }
}
