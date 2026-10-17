<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportResult;

/**
 * Satu laporan. Implementasi hanya membaca dan mengagregasi di basis data; tidak menulis apa pun (backend-ddd bagian 3).
 */
interface Report
{
    public function key(): ReportKey;

    public function run(ReportQuery $query): ReportResult;
}
