<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

use Src\Reporting\Domain\ExportFormat;
use Src\Reporting\Domain\ReportResult;

/**
 * Mengubah hasil laporan menjadi isi berkas PDF (dompdf) atau XLSX (maatwebsite/excel). Tanpa proses sistem (M21).
 */
interface ReportRenderer
{
    public function render(ExportFormat $format, ReportResult $result): string;
}
