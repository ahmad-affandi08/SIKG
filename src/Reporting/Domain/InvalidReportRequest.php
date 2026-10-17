<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

use Src\Shared\Domain\DomainException;

/**
 * Permintaan laporan yang tidak sah (rentang tanggal, laporan tidak dikenal, berkas ekspor tidak tersedia). Kode mesin
 * stabil, contoh `report.range_invalid`, `report.job_not_found`.
 */
final class InvalidReportRequest extends DomainException
{
    /**
     * @param  non-empty-string  $businessCode
     */
    public function __construct(string $businessCode, string $message)
    {
        parent::__construct($businessCode, $message);
    }

    public function httpStatus(): int
    {
        return match ($this->businessCode()) {
            'report.forbidden' => 403,
            'report.job_not_found', 'report.not_found' => 404,
            'report.job_not_ready', 'report.job_expired' => 409,
            default => 422,
        };
    }
}
