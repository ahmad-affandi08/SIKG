<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Src\IdentityAccess\Application\Login\LoginCommand;
use Src\IdentityAccess\Application\Login\LoginHandler;
use Src\IdentityAccess\Domain\InvalidCredentials;
use Src\IdentityAccess\Domain\TooManyLoginAttempts;
use Src\IdentityAccess\Presentation\Http\Requests\LoginRequest;
use Src\Shared\Application\RequiredFields;

final class LoginController
{
    public function show(Request $request): Response
    {
        return Inertia::render('Auth/Login', ['required' => RequiredFields::from(LoginRequest::createFrom($request)->rules())]);
    }

    public function store(LoginRequest $request, LoginHandler $handler): RedirectResponse
    {
        try {
            $outcome = $handler(new LoginCommand(
                identifier: trim($request->string('identifier')->toString()),
                password: $request->string('password')->toString(),
                ip: (string) $request->ip(),
            ));
        } catch (TooManyLoginAttempts|InvalidCredentials $e) {
            return back()->withErrors(['identifier' => $e->getMessage()])->onlyInput('identifier');
        }

        if ($outcome->needsSecondFactor) {
            // AUTH-05: sesi belum dimulai; hanya ID tertunda yang disimpan sampai kode kedua sah.
            $request->session()->regenerate();
            $request->session()->put(TwoFactorLoginController::SESSION_KEY, ['user_id' => $outcome->userId, 'at' => now()->getTimestamp()]);

            return redirect('/login/two-factor');
        }

        Auth::loginUsingId($outcome->userId);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
