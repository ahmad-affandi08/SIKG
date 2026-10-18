<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Queries;

use DateTimeImmutable;
use DateTimeZone;
use Src\Reporting\Contracts\OwnerDashboard;
use Src\Reporting\Domain\DateRange;

final readonly class EloquentOwnerDashboard implements OwnerDashboard
{
    private const SERIES_DAYS = 30;

    public function __construct(private ReportSource $source, private FinanceQueries $finance, private ClinicalQueries $clinical) {}

    public function snapshot(): array
    {
        $today = $this->source->today();
        $now = new DateTimeImmutable($today, new DateTimeZone('UTC'));
        $monthStart = $now->modify('first day of this month');
        $prevStart = $monthStart->modify('-1 month');
        $prevEnd = $prevStart->modify('last day of this month');
        $dayOfMonth = (int) $now->format('j');
        $prevSameEnd = $prevStart->modify('+'.(min($dayOfMonth, (int) $prevEnd->format('j')) - 1).' days');

        $todayRange = DateRange::day($today);
        $month = DateRange::between($monthStart->format('Y-m-d'), $today);
        $prevMonth = DateRange::between($prevStart->format('Y-m-d'), $prevEnd->format('Y-m-d'));
        $prevSame = DateRange::between($prevStart->format('Y-m-d'), $prevSameEnd->format('Y-m-d'));

        $revenueMonth = $this->finance->invoiceTotals($month)['total'];
        $prevSameRevenue = $this->finance->invoiceTotals($prevSame)['total'];
        $attendance = $this->clinical->attendance($month);
        $receivables = $this->finance->openReceivables();

        $seriesRange = DateRange::between($now->modify('-'.(self::SERIES_DAYS - 1).' days')->format('Y-m-d'), $today);
        $byDay = $this->finance->revenueByDay($seriesRange);
        $series = [];
        foreach ($seriesRange->dates() as $date) {
            $series[] = ['date' => $date, 'revenue' => $byDay[$date] ?? 0];
        }

        return [
            'date' => $today,
            'revenue_today' => $this->finance->invoiceTotals($todayRange)['total'],
            'revenue_month' => $revenueMonth,
            'revenue_prev_month' => $this->finance->invoiceTotals($prevMonth)['total'],
            'revenue_prev_month_same_period' => $prevSameRevenue,
            'revenue_change_basis_points' => $this->change($revenueMonth, $prevSameRevenue),
            'visits_today' => $this->clinical->visitCount($todayRange),
            'visits_month' => $this->clinical->visitCount($month),
            'no_show_count' => $attendance['no_show'],
            'no_show_basis_points' => $attendance['due'] === 0 ? 0 : intdiv($attendance['no_show'] * 20000 + $attendance['due'], $attendance['due'] * 2),
            'receivables_total' => $receivables['balance'],
            'receivables_count' => $receivables['count'],
            'low_stock_count' => $this->lowStock(),
            'series' => $series,
        ];
    }

    /** Perubahan relatif bertanda dalam basis poin, dibulatkan setengah menjauhi nol; null bila pembanding nol. */
    private function change(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return null;
        }
        $diff = $current - $previous;
        $half = intdiv($previous, 2);

        return intdiv($diff * 10000 + ($diff < 0 ? -$half : $half), $previous);
    }

    private function lowStock(): int
    {
        return $this->source->from('medicines', 'm', true, false)
            ->leftJoinSub($this->source->stockBalances(), 'b', 'b.medicine_id', '=', 'm.id')
            ->where('m.is_active', true)
            ->where('m.min_stock', '>', 0)
            ->whereRaw('COALESCE(b.quantity, 0) < m.min_stock')
            ->count();
    }
}
