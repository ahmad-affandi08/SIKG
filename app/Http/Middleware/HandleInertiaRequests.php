<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Src\Clinic\Contracts\BranchDirectory;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Application\Setup\SetupOverview;
use Src\Shared\Application\TenantContext;

final class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly BranchDirectory $branches, private readonly TenantContext $tenant) {}

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
            'branch' => $user instanceof User ? $this->branch($user) : null,
            'setup' => fn () => $this->setup($request, $user),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'error_code' => $request->session()->get('error_code'),
            ],
        ];
    }

    /**
     * Kemajuan Penyiapan awal untuk banner di Beranda (MST-10). Hanya dihitung di halaman Beranda dan untuk pemegang izin
     * pengaturan, dan null bila semua langkah wajib sudah selesai.
     *
     * @return array{done: int, total: int}|null
     */
    private function setup(Request $request, mixed $user): ?array
    {
        $clinicId = $this->tenant->clinicId();
        if (! $user instanceof User || $clinicId === null || ! $request->routeIs('dashboard') || ! $user->can('setting.manage')) {
            return null;
        }
        $progress = app(SetupOverview::class)->requiredProgress($clinicId);

        return $progress['done'] === $progress['total'] ? null : $progress;
    }

    /**
     * @return array{name: string, timezone: string, profile: string, dental: bool, doctor_title: string}|null
     */
    private function clinic(): ?array
    {
        $clinic = ClinicModel::query()->first();
        if (! $clinic instanceof ClinicModel) {
            return null;
        }
        // KLU-01: profil layanan menentukan menu dan istilah yang tampil (server tetap menutup fitur gigi lewat middleware).
        $profile = $clinic->clinicSettings()->serviceProfile;

        return ['name' => $clinic->name, 'timezone' => $clinic->timezone, 'profile' => $profile->value, 'dental' => $profile->dental(), 'doctor_title' => $profile->doctorTitle()];
    }

    /**
     * Cabang aktif untuk pemilih cabang di header (P3-04). `options` kosong bila klinik hanya punya satu cabang atau tidak ada, sehingga
     * pemilih tidak tampil. Pengguna terikat cabang melihat nama cabangnya tanpa dapat berpindah.
     *
     * @return array{active_id: string|null, active_name: string|null, locked: bool, options: list<array{id: string, name: string}>}
     */
    private function branch(User $user): array
    {
        $all = $this->branches->all();
        $active = array_values(array_filter($all, fn ($b) => $b->active));
        $activeId = $this->tenant->branchId();
        $name = null;
        foreach ($all as $branch) {
            if ($branch->id === $activeId) {
                $name = $branch->name;
            }
        }

        return [
            'active_id' => $activeId,
            'active_name' => $name,
            'locked' => $user->branch_id !== null,
            'options' => count($active) >= 2 ? array_map(fn ($b) => ['id' => $b->id, 'name' => $b->name], $active) : [],
        ];
    }
}
