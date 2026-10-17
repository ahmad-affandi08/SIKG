<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Application\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengisi konteks klinik dan cabang dari pengguna yang masuk (M3). Tanpa pengguna, konteks kosong dan
 * semua query bertenant gagal tertutup. Konteks dibersihkan setelah permintaan agar tidak bocor antar permintaan.
 */
final class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->tenant->set($user->clinic_id, $user->branch_id);
        }

        try {
            return $next($request);
        } finally {
            $this->tenant->clear();
        }
    }
}
