<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * AUTH-04: sesi berakhir otomatis setelah tidak aktif. Batas bawaan 30 menit dan dapat diatur per klinik (SYS-04),
 * tidak melebihi batas tertinggi server. Berjalan setelah konteks klinik tersedia (ResolveTenant).
 */
final class EnforceIdleTimeout
{
    private const SESSION_KEY = 'last_activity_at';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $now = Carbon::now()->getTimestamp();
        $last = $request->session()->get(self::SESSION_KEY);

        if (is_int($last) && ($now - $last) > $this->timeoutMinutes() * 60) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesi berakhir karena tidak aktif. Silakan masuk kembali.', 'code' => 'auth.session_expired'], 401);
            }

            return redirect('/login')->with('error', 'Sesi berakhir karena tidak aktif. Silakan masuk kembali.');
        }

        $request->session()->put(self::SESSION_KEY, $now);

        return $next($request);
    }

    private function timeoutMinutes(): int
    {
        $max = (int) config('sikg.session_max_minutes');
        $configured = ClinicModel::query()->first()?->clinicSettings()->sessionTimeoutMinutes
            ?? (int) config('sikg.session_default_minutes');

        return max(1, min($configured, $max));
    }
}
