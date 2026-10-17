<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Reporting\Contracts\OwnerDashboard;

/**
 * Beranda sesuai peran. Pemilik (dan Admin), yang berhak atas laporan keuangan dan klinis, juga melihat dasbor ringkasan
 * bisnis (PRD 11); peran lain hanya akses cepat.
 */
// ALASAN: beranda untuk semua pengguna yang masuk (middleware auth); dasbor bisnis hanya dimuat bila izin laporan keuangan dan klinis ada.
final class DashboardController
{
    public function __invoke(Request $request, OwnerDashboard $dashboard): Response
    {
        $user = $request->user();
        $showBusiness = $user !== null && $user->can('report.financial') && $user->can('report.clinical');

        return Inertia::render('Beranda', [
            'dashboard' => $showBusiness ? $dashboard->snapshot() : null,
        ]);
    }
}
