<?php

declare(strict_types=1);

namespace Src\Clinical\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Src\Clinical\Application\OdontogramPrint;
use Src\Shared\Application\PdfRenderer;
use Src\Shared\Application\PdfRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * ODO-06: cetak odontogram terkini pasien ke PDF (dompdf). Izin odontogram.view; pencetakan tercatat di access log dan audit.
 */
final class OdontogramPrintController
{
    public function __invoke(Request $request, string $patient, OdontogramPrint $print, PdfRenderer $renderer): Response
    {
        $actor = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($actor), 403);

        $data = $print->data($actor, $patient);
        $output = $renderer->render(new PdfRequest('pdf.odontogram', $data, 'Odontogram '.$data['patient']['name'], landscape: true));

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="odontogram-'.preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $data['patient']['medical_record_no']).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
