<?php

declare(strict_types=1);

namespace Src\Reporting\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Src\Reporting\Application\DoctorFeeSlip;
use Symfony\Component\HttpFoundation\Response;

/**
 * P2-09: slip jasa dokter (PDF). Izin `report.financial` dipasang di route.
 */
final class DoctorFeeSlipController
{
    public function __invoke(Request $request, DoctorFeeSlip $slip): Response
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'string', 'size:26'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        $file = $slip->render($id, $data['doctor_id'], $data['from'], $data['to']);

        return response($file['contents'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$file['file_name'].'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
