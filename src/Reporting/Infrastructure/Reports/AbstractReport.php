<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Reports;

use Src\Reporting\Application\Report;
use Src\Reporting\Application\ReportLookups;
use Src\Reporting\Application\ReportQuery;
use Src\Reporting\Domain\ReportColumn;
use Src\Reporting\Domain\ReportTable;

/**
 * Pembantu bersama laporan. Aturan dasar: hanya membaca, uang bilangan bulat, dan hasil dibatasi `ReportTable::MAX_ROWS`.
 */
abstract readonly class AbstractReport implements Report
{
    /** Id dokter yang tidak pernah ada: pengguna "sendiri" tanpa profil dokter tidak melihat apa pun. */
    protected const NO_DOCTOR = '00000000000000000000000000';

    /**
     * Dokter yang membatasi laporan: untuk cakupan "sendiri" dokter milik pengguna, selain itu pilihan penyaring.
     */
    protected function doctorScope(ReportQuery $query, ReportLookups $lookups): ?string
    {
        if ($query->isOwn()) {
            return $lookups->doctorIdForUser($query->userId) ?? self::NO_DOCTOR;
        }

        return $query->filters->doctorId;
    }

    /**
     * Memotong baris bila melebihi batas dan menandainya.
     *
     * @param  list<array<string, int|string|null>>  $rows
     * @param  list<ReportColumn>  $columns
     * @param  array<string, int|string|null>|null  $totals
     */
    protected function table(string $title, array $columns, array $rows, ?array $totals = null, ?string $emptyText = null): ReportTable
    {
        $truncated = count($rows) > ReportTable::MAX_ROWS;

        return new ReportTable($title, $columns, $truncated ? array_slice($rows, 0, ReportTable::MAX_ROWS) : $rows, $totals, $truncated, $emptyText);
    }

    /** Persen sebagai basis poin (1% = 100), dibulatkan setengah ke atas; nol bila penyebut nol. */
    protected function basisPoints(int $part, int $whole): int
    {
        return $whole <= 0 ? 0 : intdiv($part * 10000 * 2 + $whole, $whole * 2);
    }
}
