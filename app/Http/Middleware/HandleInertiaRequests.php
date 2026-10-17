<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\IdentityAccess\Infrastructure\Eloquent\User;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props bersama sesuai resources/js/types/inertia.d.ts. Tanpa PHI: hanya ID, nama pengguna, peran, dan
     * daftar izin. Menyembunyikan menu lewat izin hanya kenyamanan; server tetap menolak (M9).
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user instanceof User
                    ? ['id' => $user->id, 'name' => $user->name, 'roles' => $user->getRoleNames()->values()->all()]
                    : null,
                'permissions' => $user instanceof User ? $user->getAllPermissions()->pluck('name')->values()->all() : [],
            ],
            'clinic' => $user instanceof User ? $this->clinic() : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * @return array{name: string, timezone: string}|null
     */
    private function clinic(): ?array
    {
        $clinic = ClinicModel::query()->first();

        return $clinic instanceof ClinicModel ? ['name' => $clinic->name, 'timezone' => $clinic->timezone] : null;
    }
}
