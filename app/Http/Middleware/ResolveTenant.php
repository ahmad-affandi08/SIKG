<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\IdentityAccess\Application\ActiveBranch;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Application\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengisi konteks klinik dan cabang dari pengguna yang masuk (M3). Pengguna terikat satu cabang selalu bekerja di cabangnya; pengguna
 * tanpa ikatan cabang memakai cabang pilihannya di sesi (P3-04), atau melihat semua cabang bila belum memilih. Tanpa pengguna, konteks
 * kosong dan semua query bertenant gagal tertutup. Konteks dibersihkan setelah permintaan agar tidak bocor antar permintaan.
 */
final class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenant, private readonly ActiveBranch $active) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->tenant->set($user->clinic_id, $user->branch_id);
            if ($user->branch_id === null) {
                $chosen = $request->hasSession() ? $request->session()->get(ActiveBranch::SESSION_KEY) : null;
                if (is_string($chosen) && $this->active->isUsable($chosen)) {
                    $this->tenant->set($user->clinic_id, $chosen);
                }
            }
        }

        try {
            return $next($request);
        } finally {
            $this->tenant->clear();
        }
    }
}
