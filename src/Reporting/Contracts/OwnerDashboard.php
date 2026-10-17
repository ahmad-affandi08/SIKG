<?php

declare(strict_types=1);

namespace Src\Reporting\Contracts;

/**
 * Ringkasan dasbor Pemilik (PRD 11): pendapatan hari ini dan bulan ini dibanding bulan lalu, kunjungan, tingkat janji tidak
 * datang, total piutang, stok menipis, dan pendapatan 30 hari terakhir. Uang bilangan bulat rupiah; persen basis poin.
 */
interface OwnerDashboard
{
    /**
     * `revenue_prev_month_same_period` adalah pendapatan bulan lalu pada rentang hari yang sama (1 sampai tanggal hari ini),
     * pembanding yang adil untuk bulan berjalan. `revenue_change_basis_points` bertanda dan null bila pembandingnya nol.
     *
     * @return array{
     *     date: string,
     *     revenue_today: int,
     *     revenue_month: int,
     *     revenue_prev_month: int,
     *     revenue_prev_month_same_period: int,
     *     revenue_change_basis_points: int|null,
     *     visits_today: int,
     *     visits_month: int,
     *     no_show_count: int,
     *     no_show_basis_points: int,
     *     receivables_total: int,
     *     receivables_count: int,
     *     low_stock_count: int,
     *     series: list<array{date: string, revenue: int}>
     * }
     */
    public function snapshot(): array;
}
