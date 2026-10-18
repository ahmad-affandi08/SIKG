<?php

declare(strict_types=1);

namespace Src\Scheduling\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Scheduling\Application\Booking\BookingPortalResolver;
use Src\Shared\Application\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * P3-01: halaman booking publik tidak punya pengguna masuk, jadi klinik ditentukan dari alamat halaman. Slug yang tidak dikenal atau
 * booking yang dimatikan menghasilkan 404. Konteks klinik dibersihkan setelah permintaan.
 */
final class ResolveBookingClinic
{
    public function __construct(private readonly BookingPortalResolver $resolver, private readonly TenantContext $tenant) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');
        $clinicId = is_string($slug) ? $this->resolver->clinicFor($slug) : null;
        abort_if($clinicId === null, 404);

        $this->tenant->set($clinicId, null);

        try {
            return $next($request);
        } finally {
            $this->tenant->clear();
        }
    }
}
