<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda sesuai peran (isi per peran dikerjakan bersama kebutuhan terkait). Hanya untuk pengguna yang masuk.
 */
// ALASAN: beranda untuk semua pengguna yang masuk (middleware auth); tidak memuat data sensitif. Isi per peran memakai izin.
final class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('Beranda');
    }
}
