<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Src\IdentityAccess\Application\Account\AccountSecurity;
use Src\IdentityAccess\Domain\CurrentPasswordIncorrect;
use Src\IdentityAccess\Domain\PasswordUnchanged;
use Src\IdentityAccess\Presentation\Http\Requests\ChangePasswordRequest;
use Src\Shared\Application\RequiredFields;

/**
 * AUTH-06: pengguna mengganti kata sandinya sendiri, atau wajib menggantinya setelah akun dibuat atau direset.
 */
final class AccountController
{
    public function __construct(private readonly AccountSecurity $account) {}

    public function show(Request $request): Response
    {
        return Inertia::render('Account/Password', [
            'required' => RequiredFields::from(ChangePasswordRequest::createFrom($request)->rules()),
            'forced' => $this->account->mustChangePassword($this->userId($request)),
            'minLength' => ChangePasswordRequest::MIN_LENGTH,
            // AUTH-05: tautan ke verifikasi dua langkah hanya untuk Pemilik dan Admin.
            'canTwoFactor' => Gate::allows('account.two-factor'),
        ]);
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $userId = $this->userId($request);
        $wasForced = $this->account->mustChangePassword($userId);

        try {
            $this->account->changePassword($userId, $request->string('current_password')->toString(), $request->string('password')->toString(), $request->session()->getId());
        } catch (CurrentPasswordIncorrect $e) {
            return back()->withErrors(['current_password' => $e->getMessage()]);
        } catch (PasswordUnchanged $e) {
            return back()->withErrors(['password' => $e->getMessage()]);
        }

        $request->session()->regenerate();

        return ($wasForced ? redirect('/dashboard') : back())->with('success', 'Kata sandi diganti. Sesi Anda di perangkat lain telah diakhiri.');
    }

    private function userId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}
