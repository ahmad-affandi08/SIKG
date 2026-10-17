<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\IdentityAccess\Application\Roles\RoleAdministration;
use Src\IdentityAccess\Domain\Role;

/**
 * AUTH-07: halaman Peran dan izin. Izin route role.manage; aturan peran Pemilik dijaga di RoleAdministration.
 */
final class RoleController
{
    public function __construct(private readonly RoleAdministration $roles) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Roles/Index', $this->roles->overview($this->actorId($request)));
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ], ['permissions.present' => 'Daftar izin wajib dikirim.']);

        $this->roles->update($this->actorId($request), $this->role($role), array_values($data['permissions']));

        return back()->with('success', 'Izin peran disimpan. Berlaku pada permintaan berikutnya setiap pengguna peran ini.');
    }

    public function reset(Request $request, string $role): RedirectResponse
    {
        $this->roles->reset($this->actorId($request), $this->role($role));

        return back()->with('success', 'Izin peran dikembalikan ke bawaan.');
    }

    private function role(string $value): string
    {
        abort_if(Role::tryFrom($value) === null, 404);

        return $value;
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}
