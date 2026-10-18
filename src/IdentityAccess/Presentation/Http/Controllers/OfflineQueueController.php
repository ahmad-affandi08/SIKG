<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * P3-02: halaman antrean pencatatan offline. Isinya hidup di perangkat (IndexedDB terenkripsi); server tidak memuat data apa pun di sini.
 */
final class OfflineQueueController
{
    public function __invoke(): Response
    {
        return Inertia::render('Offline/Queue');
    }
}
