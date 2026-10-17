<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

use Src\Reporting\Domain\ReportScope;

/**
 * Permintaan yang sudah lolos pemeriksaan akses: penyaring, cakupan, dan pengguna peminta. Laporan hanya membaca ini.
 */
final readonly class ReportQuery
{
    public function __construct(public ReportFilters $filters, public ReportScope $scope, public string $userId) {}

    public function isOwn(): bool
    {
        return $this->scope === ReportScope::Own;
    }
}
