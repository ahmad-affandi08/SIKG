<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Reports;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Src\Reporting\Application\ReportQuery;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\DateRange;
use Src\Reporting\Domain\ReportColumn;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportMetric;
use Src\Reporting\Domain\ReportResult;
use Src\Reporting\Domain\ReportTable;
use Src\Reporting\Infrastructure\Queries\FinanceQueries;
use Src\Reporting\Infrastructure\Queries\LocalTime;
use Src\Reporting\Infrastructure\Queries\ReportSource;
use stdClass;

/**
 * LAP-11 (P2-08): laba rugi sederhana per bulan. Pendapatan = tagihan final menurut tanggal terbit (sama dengan LAP-02). Pengurang:
 * biaya lab pekerjaan yang diterima, pembelian obat dan bahan yang diterima dari pemasok (batch berharga beli), dan biaya
 * operasional yang dicatat (tidak batal). Bukan laporan akuntansi penuh: tanpa penyusutan, pajak penghasilan, dan hutang piutang.
 */
final readonly class ProfitLossReport extends AbstractReport
{
    public function __construct(private FinanceQueries $finance, private ReportSource $source) {}

    public function key(): ReportKey
    {
        return ReportKey::ProfitLoss;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $range = $query->filters->range;
        $offset = $this->source->offset($range);

        $revenue = $this->byMonth($this->finance->finalInvoices($range)->groupBy('period')
            ->selectRaw(LocalTime::INVOICE_ISSUED_MONTH.' as period, COALESCE(SUM(i.total_amount), 0) as total', [$offset])->get());
        $lab = $this->byMonth($this->source->from('lab_orders', 'lo')
            ->where('lo.status', 'received')->whereBetween('lo.received_date', [$range->from, $range->to])->groupBy('period')
            ->selectRaw("DATE_FORMAT(lo.received_date, '%Y-%m') as period, COALESCE(SUM(lo.lab_cost_amount), 0) as total")->get());
        $purchases = $this->byMonth($this->source->within($this->source->from('stock_batches', 'sb')->whereNotNull('sb.unit_cost_amount')->whereNull('sb.transfer_id'), 'sb.received_at', $range)->groupBy('period')
            ->selectRaw(LocalTime::STOCK_BATCH_RECEIVED_MONTH.' as period, COALESCE(SUM(sb.unit_cost_amount * sb.received_quantity), 0) as total', [$offset])->get());
        $operating = $this->byMonth($this->expenses($range)->groupBy('period')
            ->selectRaw("DATE_FORMAT(x.expense_date, '%Y-%m') as period, COALESCE(SUM(x.amount), 0) as total")->get());

        $rows = [];
        $sum = ['period' => 'Total', 'revenue' => 0, 'lab' => 0, 'purchases' => 0, 'operating' => 0, 'profit' => 0];
        foreach ($this->months($range->from, $range->to) as $month) {
            $row = [
                'period' => $month,
                'revenue' => $revenue[$month] ?? 0,
                'lab' => $lab[$month] ?? 0,
                'purchases' => $purchases[$month] ?? 0,
                'operating' => $operating[$month] ?? 0,
            ];
            $row['profit'] = $row['revenue'] - $row['lab'] - $row['purchases'] - $row['operating'];
            $rows[] = $row;
            foreach (['revenue', 'lab', 'purchases', 'operating', 'profit'] as $k) {
                $sum[$k] += $row[$k];
            }
        }

        $metrics = [
            new ReportMetric('Pendapatan', $sum['revenue'], ColumnKind::Money),
            new ReportMetric('Biaya lab', $sum['lab'], ColumnKind::Money),
            new ReportMetric('Pembelian obat dan bahan', $sum['purchases'], ColumnKind::Money),
            new ReportMetric('Biaya operasional', $sum['operating'], ColumnKind::Money),
            new ReportMetric('Laba (rugi) sederhana', $sum['profit'], ColumnKind::Money),
            new ReportMetric('Margin laba', $this->basisPoints(max(0, $sum['profit']), $sum['revenue']), ColumnKind::Percent),
        ];

        return new ReportResult($this->key(), $range, $metrics, [$this->monthTable($rows, $sum), $this->categoryTable($range)], [
            'Pendapatan mengikuti tagihan terbit menurut tanggal terbit (sama dengan laporan Pendapatan), bukan uang yang sudah diterima.',
            'Biaya lab = biaya aktual pesanan lab yang diterima pada bulan itu. Pembelian obat dan bahan = nilai batch berharga beli yang diterima dari pemasok; penerimaan stok manual tanpa harga beli tidak dihitung.',
            'Jasa dokter, gaji, dan sewa dicatat sebagai biaya operasional bila ingin masuk laba rugi. Laporan ini bukan laporan akuntansi penuh.',
        ]);
    }

    /**
     * @param  list<array<string, int|string|null>>  $rows
     * @param  array<string, int|string|null>  $sum
     */
    private function monthTable(array $rows, array $sum): ReportTable
    {
        return $this->table(
            'Laba rugi per bulan',
            [
                new ReportColumn('period', 'Bulan'),
                new ReportColumn('revenue', 'Pendapatan', ColumnKind::Money),
                new ReportColumn('lab', 'Biaya lab', ColumnKind::Money),
                new ReportColumn('purchases', 'Pembelian obat', ColumnKind::Money),
                new ReportColumn('operating', 'Biaya operasional', ColumnKind::Money),
                new ReportColumn('profit', 'Laba (rugi)', ColumnKind::Money),
            ],
            $rows,
            $rows === [] ? null : $sum,
            'Tidak ada data pada rentang ini.',
        );
    }

    private function categoryTable(DateRange $range): ReportTable
    {
        $rows = $this->expenses($range)->groupBy('x.category_id')
            ->selectRaw('x.category_id as category_id, COUNT(*) as n, COALESCE(SUM(x.amount), 0) as total')->get();
        $names = $this->source->names('expense_categories', array_map(fn ($r) => (string) $r->category_id, $rows->all()));

        $list = [];
        foreach ($rows as $r) {
            $list[] = ['category' => $names[(string) $r->category_id] ?? '(kategori tidak dikenal)', 'count' => (int) $r->n, 'total' => (int) $r->total];
        }
        usort($list, fn (array $a, array $b) => [$b['total'], $a['category']] <=> [$a['total'], $b['category']]);

        return $this->table(
            'Biaya operasional per kategori',
            [new ReportColumn('category', 'Kategori'), new ReportColumn('count', 'Jumlah catatan', ColumnKind::Integer), new ReportColumn('total', 'Total', ColumnKind::Money)],
            $list,
            $list === [] ? null : ['category' => 'Total', 'count' => array_sum(array_column($list, 'count')), 'total' => array_sum(array_column($list, 'total'))],
            'Belum ada biaya operasional pada rentang ini.',
        );
    }

    private function expenses(DateRange $range): Builder
    {
        return $this->source->from('expenses', 'x')->where('x.status', 'recorded')->whereBetween('x.expense_date', [$range->from, $range->to]);
    }

    /**
     * @param  iterable<stdClass>  $rows
     * @return array<string, int>
     */
    private function byMonth(iterable $rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r->period] = (int) $r->total;
        }

        return $map;
    }

    /**
     * @return list<string> bulan YYYY-MM dari bulan tanggal awal sampai bulan tanggal akhir
     */
    private function months(string $from, string $to): array
    {
        $cursor = new DateTimeImmutable(substr($from, 0, 7).'-01', new DateTimeZone('UTC'));
        $last = substr($to, 0, 7);
        $months = [];
        while ($cursor->format('Y-m') <= $last) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }

        return $months;
    }
}
