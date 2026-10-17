<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Reports;

use Src\Reporting\Application\ReportQuery;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportColumn;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportMetric;
use Src\Reporting\Domain\ReportResult;
use Src\Reporting\Domain\ReportTable;
use Src\Reporting\Infrastructure\Queries\ReportSource;

/**
 * LAP-08: tutup kas per kasir per hari (sistem, fisik, selisih). Kasir hanya melihat penutupan miliknya.
 */
final readonly class CashClosingReport extends AbstractReport
{
    public function __construct(private ReportSource $source) {}

    public function key(): ReportKey
    {
        return ReportKey::CashClosing;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $f = $query->filters;
        $rows = $this->source->from('cash_closings', 'c', false)
            ->whereBetween('c.closing_date', [$f->range->from, $f->range->to])
            ->when($query->isOwn(), fn ($q) => $q->where('c.cashier_id', $query->userId))
            ->orderBy('c.closing_date')->orderBy('c.closed_at')
            ->limit(ReportTable::MAX_ROWS + 1)
            ->get(['c.closing_date', 'c.cashier_id', 'c.system_amount', 'c.counted_amount', 'c.difference_amount', 'c.note', 'c.closed_at']);
        $names = $this->source->names('users', array_map(fn ($r) => (string) $r->cashier_id, $rows->all()));

        $list = [];
        $sum = ['date' => null, 'cashier' => 'Total', 'system' => 0, 'counted' => 0, 'difference' => 0, 'note' => null];
        $withDifference = 0;
        foreach ($rows as $r) {
            $row = [
                'date' => substr((string) $r->closing_date, 0, 10), 'cashier' => $names[(string) $r->cashier_id] ?? '-', 'system' => (int) $r->system_amount,
                'counted' => (int) $r->counted_amount, 'difference' => (int) $r->difference_amount, 'note' => (string) ($r->note ?? ''),
            ];
            $list[] = $row;
            $sum['system'] += $row['system'];
            $sum['counted'] += $row['counted'];
            $sum['difference'] += $row['difference'];
            $withDifference += $row['difference'] !== 0 ? 1 : 0;
        }

        return new ReportResult($this->key(), $f->range, [
            new ReportMetric('Penutupan kas', count($list)),
            new ReportMetric('Tunai menurut sistem', $sum['system'], ColumnKind::Money),
            new ReportMetric('Uang fisik', $sum['counted'], ColumnKind::Money),
            new ReportMetric('Selisih', $sum['difference'], ColumnKind::Money),
            new ReportMetric('Penutupan dengan selisih', $withDifference),
        ], [
            $this->table(
                'Penutupan kas per kasir per hari',
                [
                    new ReportColumn('date', 'Tanggal', ColumnKind::Date), new ReportColumn('cashier', 'Kasir'), new ReportColumn('system', 'Sistem', ColumnKind::Money),
                    new ReportColumn('counted', 'Fisik', ColumnKind::Money), new ReportColumn('difference', 'Selisih', ColumnKind::Money), new ReportColumn('note', 'Catatan'),
                ],
                $list,
                $list === [] ? null : $sum,
                'Belum ada penutupan kas pada rentang ini.',
            ),
            $this->unclosed($query),
        ], [
            'Selisih = uang fisik dikurangi catatan sistem; positif berarti kelebihan, negatif kekurangan.',
            'Uang tunai belum ditutup adalah pembayaran tunai pada rentang yang belum tercakup penutupan kas siapa pun.',
            ...($query->isOwn() ? ['Hanya penutupan dan penerimaan tunai yang Anda catat sendiri.'] : []),
        ]);
    }

    private function unclosed(ReportQuery $query): ReportTable
    {
        $f = $query->filters;
        $base = $this->source->from('payments', 'p', false)
            ->join('payment_methods as m', 'm.id', '=', 'p.payment_method_id')
            ->where('m.type', 'cash')
            ->whereNull('p.cash_closing_id')
            ->when($query->isOwn(), fn ($q) => $q->where('p.received_by', $query->userId));
        $this->source->within($base, 'p.paid_at', $f->range);
        $rows = $base->groupBy('p.received_by')
            ->selectRaw("p.received_by as cashier_id, COUNT(*) as n, COALESCE(SUM(CASE WHEN p.type = 'refund' THEN -p.amount ELSE p.amount END), 0) as net")
            ->get();
        $names = $this->source->names('users', array_map(fn ($r) => (string) $r->cashier_id, $rows->all()));

        $list = [];
        foreach ($rows as $r) {
            $list[] = ['cashier' => $names[(string) $r->cashier_id] ?? '-', 'count' => (int) $r->n, 'amount' => (int) $r->net];
        }

        return $this->table(
            'Uang tunai belum ditutup',
            [new ReportColumn('cashier', 'Kasir'), new ReportColumn('count', 'Transaksi', ColumnKind::Integer), new ReportColumn('amount', 'Tunai bersih', ColumnKind::Money)],
            $list,
            null,
            'Semua uang tunai pada rentang ini sudah ditutup.',
        );
    }
}
