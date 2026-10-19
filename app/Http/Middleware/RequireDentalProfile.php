<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Clinic\Contracts\ServiceProfileLookup;
use Symfony\Component\HttpFoundation\Response;

/**
 * KLU-01: fitur khusus gigi (odontogram, ortodonti, pesanan lab gigi) ditutup di server pada klinik berprofil umum.
 * Menyembunyikan menu saja bukan pengamanan (M9); permintaan langsung ke alamatnya dijawab 404 seolah fitur itu tidak ada.
 * Data yang sudah tersimpan tidak disentuh.
 */
final class RequireDentalProfile
{
    public function __construct(private readonly ServiceProfileLookup $profile) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->profile->current()->dental(), 404, 'Fitur ini tidak tersedia pada profil layanan klinik Anda.');

        return $next($request);
    }
}
