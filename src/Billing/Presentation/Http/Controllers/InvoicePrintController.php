<?php

declare(strict_types=1);

namespace Src\Billing\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Src\Billing\Application\InvoicePrint;
use Src\Shared\Application\PdfRenderer;
use Src\Shared\Application\PdfRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * KSR-06: cetak invoice dan kwitansi. `doc=invoice|receipt`, `format=a4|thermal`, `payment=<id>` untuk kwitansi tertentu,
 * `download=1` untuk berkas PDF yang dapat dibagikan. Izin invoice.view.
 */
final class InvoicePrintController
{
    public function __invoke(Request $request, InvoicePrint $print, PdfRenderer $renderer, string $invoice): Response
    {
        $query = $request->validate([
            'doc' => ['nullable', 'in:invoice,receipt'],
            'format' => ['nullable', 'in:a4,thermal'],
            'payment' => ['nullable', 'string', 'size:26'],
            'download' => ['nullable', 'boolean'],
        ]);
        $doc = $query['doc'] ?? 'invoice';
        $thermal = ($query['format'] ?? 'a4') === 'thermal';

        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        $data = $print->data($id, $invoice, $doc, $query['payment'] ?? null);
        $data['thermal'] = $thermal;

        // 80 mm = 226,77 pt; tinggi struk menyesuaikan jumlah baris agar tidak terpotong.
        $rows = count($data['items']) + count($data['payments']);
        $title = $doc === 'receipt' ? 'Kwitansi '.($data['receipt']['receipt_no'] ?? '') : 'Invoice '.($data['invoice']['number'] ?? 'Draft');
        $output = $renderer->render(new PdfRequest('pdf.invoice', $data, $title, customSizePt: $thermal ? [226.77, 330.0 + 26 * $rows] : null, pageNumbers: ! $thermal));

        $name = ($doc === 'receipt' ? 'kwitansi-' : 'invoice-').preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($doc === 'receipt' ? ($data['receipt']['receipt_no'] ?? 'kwitansi') : $data['invoice']['number'])).'.pdf';
        $download = (bool) ($query['download'] ?? false);

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
