<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\IdentityAccess\Application\TwoFactor\TwoFactorSetup;
use Src\IdentityAccess\Domain\CurrentPasswordIncorrect;
use Src\IdentityAccess\Domain\TwoFactor\TwoFactorFailed;
use Src\IdentityAccess\Presentation\Http\Requests\TwoFactorCodeRequest;

/**
 * AUTH-05: Pemilik dan Admin mengaktifkan atau mematikan verifikasi dua langkah miliknya. Gate account.two-factor di route.
 * Kode pemulihan hanya tampil pada respons setelah dibuat (lewat flash sesi satu permintaan).
 */
final class TwoFactorController
{
    public function __construct(private readonly TwoFactorSetup $setup) {}

    public function show(Request $request): Response
    {
        return Inertia::render('Account/TwoFactor', [
            'status' => $this->setup->status($this->userId($request)),
            'recoveryCodes' => $request->session()->get('recovery_codes'),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        try {
            $this->setup->begin($this->userId($request));
        } catch (TwoFactorFailed $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return to_route('account.two-factor');
    }

    public function confirm(TwoFactorCodeRequest $request): RedirectResponse
    {
        return $this->guarded(fn () => $this->setup->confirm($this->userId($request), $request->string('password')->toString(), $request->string('code')->toString()), 'Verifikasi dua langkah aktif. Simpan kode pemulihan di tempat aman.', true);
    }

    public function disable(TwoFactorCodeRequest $request): RedirectResponse
    {
        return $this->guarded(function () use ($request): void {
            $this->setup->disable($this->userId($request), $request->string('password')->toString(), $request->string('code')->toString());
        }, 'Verifikasi dua langkah dimatikan.', false);
    }

    public function regenerate(TwoFactorCodeRequest $request): RedirectResponse
    {
        return $this->guarded(fn () => $this->setup->regenerateRecovery($this->userId($request), $request->string('password')->toString(), $request->string('code')->toString()), 'Kode pemulihan baru dibuat. Kode lama tidak berlaku lagi.', true);
    }

    /**
     * @param  callable(): mixed  $action
     */
    private function guarded(callable $action, string $success, bool $withCodes): RedirectResponse
    {
        try {
            $result = $action();
        } catch (CurrentPasswordIncorrect $e) {
            return back()->withErrors(['password' => $e->getMessage()]);
        } catch (TwoFactorFailed $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $response = to_route('account.two-factor')->with('success', $success);

        return $withCodes ? $response->with('recovery_codes', $result) : $response;
    }

    private function userId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}
