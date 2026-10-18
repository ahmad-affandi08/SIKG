<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Reports;

use Src\Reporting\Application\ReportQuery;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportColumn;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportMetric;
use Src\Reporting\Domain\ReportResult;
use Src\Reporting\Infrastructure\Queries\ClinicalQueries;
use Src\Reporting\Infrastructure\Queries\FinanceQueries;
use Src\Reporting\Infrastructure\Queries\ReportSource;
use stdClass;

/**
 * LAP-12 (P3-04): laporan konsolidasi cabang. Satu baris per cabang dengan pendapatan (tagihan terbit), penerimaan bersih, kunjungan, dan
 * biaya operasional, ditutup baris Total. Mengikuti cabang aktif: pengguna terikat cabang atau yang memilih satu cabang hanya melihat
 * cabang itu; "Semua cabang" menampilkan seluruhnya. Data tanpa cabang (gudang pusat atau klinik satu lokasi) dilaporkan sebagai "Tanpa cabang".
 */
final readonly class BranchSummaryReport extends AbstractReport
{
    private const NONE = '';

    public function __construct(private FinanceQueries $finance, private ClinicalQueries $clinical, private ReportSource $source) {}

    public function key(): ReportKey
    {
        return ReportKey::BranchSummary;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $range = $query->filters->range;

        $revenue = $this->keyed($this->finance->finalInvoices($range)->groupBy('i.branch_id')
            ->selectRaw('i.branch_id as b, COUNT(*) as n, COALESCE(SUM(i.total_amount), 0) as total')->get(), 'n', 'total');
        $receipts = $this->keyed($this->source->within(
            $this->source->from('payments', 'p', false, false)->join('invoices as i', 'i.id', '=', 'p.invoice_id')->when($this->scoped(), fn ($q) => $q->where('i.branch_id', $this->scopeBranch())),
            'p.paid_at',
            $range,
        )->groupBy('i.branch_id')->selectRaw("i.branch_id as b, COUNT(*) as n, COALESCE(SUM(CASE WHEN p.type = 'refund' THEN -p.amount ELSE p.amount END), 0) as total")->get(), 'n', 'total');
        $visits = $this->keyed($this->clinical->encounters($range)->groupBy('e.branch_id')->selectRaw('e.branch_id as b, COUNT(*) as n')->get(), 'n', 'n');
        $expenses = $this->keyed($this->source->from('expenses', 'x')->where('x.status', 'recorded')->whereBetween('x.expense_date', [$range->from, $range->to])
            ->groupBy('x.branch_id')->selectRaw('x.branch_id as b, COUNT(*) as n, COALESCE(SUM(x.amount), 0) as total')->get(), 'n', 'total');

        $ids = array_values(array_unique([...array_keys($revenue), ...array_keys($receipts), ...array_keys($visits), ...array_keys($expenses)]));
        $names = $this->source->names('branches', array_values(array_filter($ids, fn (string $id) => $id !== self::NONE)));

        $rows = [];
        $sum = ['branch' => 'Total', 'invoices' => 0, 'revenue' => 0, 'receipts' => 0, 'visits' => 0, 'expenses' => 0, 'balance' => 0];
        foreach ($ids as $id) {
            $row = [
                'branch' => $id === self::NONE ? 'Tanpa cabang' : ($names[$id] ?? 'Cabang tidak dikenal'),
                'invoices' => $revenue[$id]['count'] ?? 0,
                'revenue' => $revenue[$id]['total'] ?? 0,
                'receipts' => $receipts[$id]['total'] ?? 0,
                'visits' => $visits[$id]['total'] ?? 0,
                'expenses' => $expenses[$id]['total'] ?? 0,
            ];
            $row['balance'] = $row['receipts'] - $row['expenses'];
            $rows[] = $row;
            foreach (['invoices', 'revenue', 'receipts', 'visits', 'expenses', 'balance'] as $k) {
                $sum[$k] += $row[$k];
            }
        }
        usort($rows, fn (array $a, array $b) => $b['revenue'] <=> $a['revenue'] ?: strcmp((string) $a['branch'], (string) $b['branch']));

        return new ReportResult($this->key(), $range, [
            new ReportMetric('Cabang dengan aktivitas', count($rows)),
            new ReportMetric('Pendapatan (tagihan terbit)', $sum['revenue'], ColumnKind::Money),
            new ReportMetric('Penerimaan bersih', $sum['receipts'], ColumnKind::Money),
            new ReportMetric('Kunjungan', $sum['visits']),
            new ReportMetric('Biaya operasional', $sum['expenses'], ColumnKind::Money),
        ], [$this->table(
            'Per cabang',
            [
                new ReportColumn('branch', 'Cabang'),
                new ReportColumn('invoices', 'Tagihan terbit', ColumnKind::Integer),
                new ReportColumn('revenue', 'Pendapatan', ColumnKind::Money),
                new ReportColumn('receipts', 'Penerimaan bersih', ColumnKind::Money),
                new ReportColumn('visits', 'Kunjungan', ColumnKind::Integer),
                new ReportColumn('expenses', 'Biaya operasional', ColumnKind::Money),
                new ReportColumn('balance', 'Penerimaan - biaya', ColumnKind::Money),
            ],
            $rows,
            $rows === [] ? null : $sum,
            'Tidak ada data pada rentang ini.',
        )], [
            'Setiap angka dihitung dari cabang yang tercatat pada data itu: tagihan dan kunjungan menurut cabang pencatatnya, penerimaan menurut cabang tagihan, biaya menurut cabang pencatat biaya.',
            'Pendapatan = tagihan terbit menurut tanggal terbit; penerimaan bersih = uang masuk dikurangi refund menurut tanggal bayar. Selisih "Penerimaan - biaya" bukan laba (lihat Laba rugi sederhana).',
        ]);
    }

    /** Pengguna yang memilih satu cabang hanya melihat cabang itu; penerimaan memfilter lewat cabang tagihan. */
    private function scoped(): bool
    {
        return $this->scopeBranch() !== null;
    }

    private function scopeBranch(): ?string
    {
        return $this->source->branchId();
    }

    /**
     * @param  iterable<stdClass>  $rows
     * @return array<string, array{count: int, total: int}>
     */
    private function keyed(iterable $rows, string $countColumn, string $totalColumn): array
    {
        $map = [];
        foreach ($rows as $r) {
            $key = $r->b === null ? self::NONE : (string) $r->b;
            $map[$key] = ['count' => (int) ($r->{$countColumn} ?? 0), 'total' => (int) ($r->{$totalColumn} ?? 0)];
        }

        return $map;
    }
}
