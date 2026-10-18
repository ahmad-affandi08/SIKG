<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Batas laju (`throttle:N,M`) yang hitungannya terpisah per rute. Bawaan Laravel memakai satu hitungan untuk semua rute per IP
 * (atau per pengguna), sehingga halaman yang sering memanggil rute lain, misalnya memuat slot booking atau polling status QR,
 * menghabiskan jatah rute tulis yang batasnya jauh lebih kecil dan membuat pengguna sah menerima 429.
 */
final class RouteScopedThrottle extends ThrottleRequests
{
    protected function resolveRequestSignature($request): string
    {
        if (! $request instanceof Request) {
            return parent::resolveRequestSignature($request);
        }
        $user = $request->user();
        $who = $user !== null ? 'user:'.(string) $user->getAuthIdentifier() : 'ip:'.(string) $request->ip();
        $route = $request->route();
        $scope = $request->method().'|'.($route !== null && is_object($route) && method_exists($route, 'uri') ? (string) $route->uri() : $request->path());

        return sha1($scope.'|'.$who);
    }
}
