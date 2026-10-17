<?php

declare(strict_types=1);

namespace Src\Clinical\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Clinical\Application\OdontogramComparison;

/**
 * ODO-07: halaman bandingkan odontogram dua kunjungan berdampingan. Izin odontogram.view dipasang di route.
 */
final class OdontogramCompareController
{
    public function __invoke(Request $request, string $patient, OdontogramComparison $compare): Response
    {
        $query = $request->validate([
            'left' => ['nullable', 'string', 'size:26'],
            'right' => ['nullable', 'string', 'size:26'],
        ]);

        $data = $compare($patient, $query['left'] ?? null, $query['right'] ?? null, 'odontogram.compare');

        return Inertia::render('Patients/OdontogramCompare', $data);
    }
}
