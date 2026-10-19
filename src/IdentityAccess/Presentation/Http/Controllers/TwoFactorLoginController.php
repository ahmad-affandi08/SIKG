<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Src\Clinic\Contracts\LoginBranding;
use Src\IdentityAccess\Application\Login\TwoFactorChallenge;
use Src\IdentityAccess\Domain\InvalidCredentials;
use Src\IdentityAccess\Domain\TooManyLoginAttempts;
use Src\IdentityAccess\Domain\TwoFactor\TwoFactorFailed;
use Src\IdentityAccess\Presentation\Http\Requests\TwoFactorLoginRequest;

/**
 * AUTH-05: langkah kedua masuk. Hanya dapat dibuka setelah kata sandi benar (ID pengguna tertunda di sesi, berlaku 5 menit).
 */
final class TwoFactorLoginController
{
    public const SESSION_KEY = 'two_factor';

    private const VALID_SECONDS = 300;

    public function show(Request $request, LoginBranding $branding): Response|RedirectResponse
    {
        return $this->pendingUser($request) === null ? $this->expired($request) : Inertia::render('Auth/TwoFactor', ['brand' => LoginController::brandProps($branding)]);
    }

    public function store(TwoFactorLoginRequest $request, TwoFactorChallenge $challenge): RedirectResponse
    {
        $userId = $this->pendingUser($request);
        if ($userId === null) {
            return $this->expired($request);
        }

        try {
            $id = $challenge($userId, $request->string('code')->toString(), (string) $request->ip());
        } catch (TooManyLoginAttempts|TwoFactorFailed $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        } catch (InvalidCredentials) {
            return $this->expired($request);
        }

        $request->session()->forget(self::SESSION_KEY);
        Auth::loginUsingId($id);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    private function pendingUser(Request $request): ?string
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! is_array($pending) || ! is_string($pending['user_id'] ?? null) || ! is_int($pending['at'] ?? null)) {
            return null;
        }

        return now()->getTimestamp() - $pending['at'] <= self::VALID_SECONDS ? $pending['user_id'] : null;
    }

    private function expired(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect('/login')->withErrors(['identifier' => 'Waktu verifikasi habis. Masuk kembali.']);
    }
}
