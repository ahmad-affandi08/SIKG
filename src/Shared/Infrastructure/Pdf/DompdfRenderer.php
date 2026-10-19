<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Src\Shared\Application\PdfRenderer;
use Src\Shared\Application\PdfRequest;
use Src\Shared\Domain\Brand;

/**
 * dompdf (PHP murni, M21). Penomoran halaman memakai kanvas dompdf, bukan PHP di dalam templat, sehingga opsi PHP dompdf
 * tetap mati. Total halaman baru diketahui setelah tata letak selesai, karena itu dipasang setelah render.
 */
final class DompdfRenderer implements PdfRenderer
{
    /** Jarak teks nomor halaman dari tepi bawah dan samping kertas A4 (poin); selaras margin pada pdf/partials/style. */
    private const FOOTER_FROM_BOTTOM = 44.0;

    private const SIDE_MARGIN = 51.0;

    public function render(PdfRequest $request): string
    {
        $pdf = Pdf::loadView($request->view, $request->data);
        if ($request->customSizePt !== null) {
            $pdf->setPaper([0.0, 0.0, $request->customSizePt[0], $request->customSizePt[1]], 'portrait');
        } else {
            $pdf->setPaper('a4', $request->landscape ? 'landscape' : 'portrait');
        }

        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $dompdf->addInfo('Title', $request->title);
        $dompdf->addInfo('Creator', Brand::NAME);
        $dompdf->addInfo('Producer', Brand::NAME.' (dompdf)');

        if ($request->pageNumbers) {
            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('helvetica') ?? 'helvetica';
            $size = 7.0;
            $text = 'Halaman {PAGE_NUM} dari {PAGE_COUNT}';
            $width = $dompdf->getFontMetrics()->getTextWidth('Halaman 00 dari 00', $font, $size);
            $canvas->page_text($canvas->get_width() - self::SIDE_MARGIN - $width, $canvas->get_height() - self::FOOTER_FROM_BOTTOM, $text, $font, $size, [0.33, 0.33, 0.33]);
        }

        return $pdf->output();
    }
}
