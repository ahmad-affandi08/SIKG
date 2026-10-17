<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * AUTH-06: kata sandi sementara (akun baru atau hasil reset) wajib diganti sebelum memakai fitur lain.
 * Hanya halaman ganti kata sandi dan keluar yang tetap terbuka.
 */
final class EnforcePasswordChange
{
    private const TARGET = '/account/password';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->is(ltrim(self::TARGET, '/'), 'logout')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('internal-api/*')) {
            return response()->json(['message' => 'Ganti kata sandi sementara Anda lebih dulu.', 'code' => 'auth.password_change_required'], 403);
        }

        return redirect(self::TARGET)->with('error', 'Ganti kata sandi sementara Anda lebih dulu sebelum melanjutkan.');
    }
}
