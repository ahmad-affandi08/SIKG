<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\IdentityAccess\Application\Users\UserAdministration;
use Src\IdentityAccess\Application\Users\UserCredential;
use Src\IdentityAccess\Application\Users\UserData;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Presentation\Http\Requests\UserRequest;
use Src\Shared\Application\RequiredFields;

/**
 * AUTH-03. Izin route (can:user.view/user.manage) ditambah Policy per pengguna sasaran di dalam UserAdministration.
 * Kata sandi sementara hanya tampil pada respons langsung (tidak lewat sesi atau log).
 */
final class UserController
{
    public function __construct(private readonly UserAdministration $users) {}

    public function index(Request $request): Response
    {
        $actor = $this->actorId($request);

        return Inertia::render('Settings/Users/Index', [
            'users' => $this->users->list($actor),
            'canCreate' => $this->users->canCreate($actor),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Settings/Users/Form', [
            'required' => RequiredFields::from(UserRequest::createFrom($request)->rules()),
            'user' => null,
            'roles' => $this->users->assignableRoles($this->actorId($request)),
            'branches' => $this->users->branchOptions(),
        ]);
    }

    public function store(UserRequest $request): Response
    {
        return $this->credential($this->users->create($this->actorId($request), $this->data($request)));
    }

    public function edit(Request $request, string $user): Response
    {
        $actor = $this->actorId($request);
        $found = $this->users->find($actor, $user);

        return Inertia::render('Settings/Users/Form', [
            'required' => RequiredFields::from(UserRequest::createFrom($request)->rules()),
            'user' => collect($found)->except('canManage')->all(),
            'roles' => $this->users->assignableRoles($actor),
            'branches' => $this->users->branchOptions(),
            'canManage' => $found['canManage'],
        ]);
    }

    public function update(UserRequest $request, string $user): RedirectResponse
    {
        $this->users->update($this->actorId($request), $user, $this->data($request));

        return redirect('/settings/users')->with('success', 'Pengguna diperbarui.');
    }

    public function deactivate(Request $request, string $user): RedirectResponse
    {
        $this->users->deactivate($this->actorId($request), $user);

        return back()->with('success', 'Pengguna dinonaktifkan. Riwayatnya tetap tersimpan.');
    }

    public function activate(Request $request, string $user): RedirectResponse
    {
        $this->users->activate($this->actorId($request), $user);

        return back()->with('success', 'Pengguna diaktifkan kembali.');
    }

    public function resetPassword(Request $request, string $user): Response
    {
        return $this->credential($this->users->resetPassword($this->actorId($request), $user));
    }

    public function resetTwoFactor(Request $request, string $user): RedirectResponse
    {
        $this->users->resetTwoFactor($this->actorId($request), $user);

        return back()->with('success', 'Verifikasi dua langkah pengguna dimatikan. Ia dapat mengaktifkannya lagi setelah masuk.');
    }

    private function credential(UserCredential $credential): Response
    {
        return Inertia::render('Settings/Users/Credential', [
            'username' => $credential->username,
            'name' => $credential->name,
            'temporaryPassword' => $credential->temporaryPassword,
        ]);
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }

    private function data(UserRequest $request): UserData
    {
        $branch = $request->string('branch_id')->toString();

        return new UserData(
            name: trim($request->string('name')->toString()),
            email: mb_strtolower(trim($request->string('email')->toString())),
            username: trim($request->string('username')->toString()),
            role: Role::from($request->string('role')->toString()),
            branchId: $branch === '' ? null : $branch,
        );
    }
}
