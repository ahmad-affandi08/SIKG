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
use Src\Reporting\Infrastructure\Queries\ClinicalQueries;
use Src\Reporting\Infrastructure\Queries\FinanceQueries;

/**
 * LAP-01: ringkasan harian. Untuk Kasir (cakupan "sendiri") penerimaan hanya yang ia catat dan angka tagihan klinik
 * tidak ditampilkan.
 */
final readonly class DailySummaryReport extends AbstractReport
{
    private const TOP_PROCEDURES = 10;

    public function __construct(private FinanceQueries $finance, private ClinicalQueries $clinical) {}

    public function key(): ReportKey
    {
        return ReportKey::DailySummary;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $f = $query->filters;
        $own = $query->isOwn();
        $byMethod = $this->finance->receiptsByMethod($f->range, $f->doctorId, $f->methodId, $own ? $query->userId : null);

        $received = 0;
        $payments = 0;
        $methodRows = [];
        foreach ($byMethod as $m) {
            $received += $m['net'];
            $payments += $m['count'];
            $methodRows[] = ['method' => $m['method'], 'count' => $m['count'], 'pay' => $m['pay'], 'dp' => $m['dp'], 'refund' => $m['refund'], 'net' => $m['net']];
        }
        $methodTotals = ['method' => 'Total', 'count' => $payments, 'pay' => array_sum(array_column($methodRows, 'pay')), 'dp' => array_sum(array_column($methodRows, 'dp')), 'refund' => array_sum(array_column($methodRows, 'refund')), 'net' => $received];

        $visits = $this->clinical->visitCount($f->range, $f->doctorId);
        $newPatients = $this->clinical->newPatientCount($f->range, $f->doctorId);

        $metrics = [new ReportMetric('Penerimaan bersih', $received, ColumnKind::Money)];
        $notes = [
            'Penerimaan bersih = pembayaran dan uang muka dikurangi refund pada rentang tanggal bayar.',
            'Pasien baru = pasien yang kunjungan pertamanya jatuh dalam rentang.',
        ];
        if ($own) {
            $metrics[] = new ReportMetric('Transaksi pembayaran Anda', $payments);
            $notes[] = 'Sebagai kasir, penerimaan hanya yang Anda catat sendiri; angka tagihan seluruh klinik tidak ditampilkan.';
        } else {
            $totals = $this->finance->invoiceTotals($f->range, $f->doctorId, $f->methodId);
            $receivables = $this->finance->finalInvoices($f->range, $f->doctorId, $f->methodId)
                ->whereColumn('i.paid_amount', '<', 'i.total_amount')
                ->selectRaw('COUNT(*) as n, COALESCE(SUM(i.total_amount - i.paid_amount), 0) as balance')->first();
            $voided = $this->finance->voidedInvoices($f->range, $f->doctorId)->count();
            $metrics[] = new ReportMetric('Pendapatan (tagihan terbit)', $totals['total'], ColumnKind::Money);
            $metrics[] = new ReportMetric('Piutang baru', (int) ($receivables->balance ?? 0), ColumnKind::Money);
            $metrics[] = new ReportMetric('Tagihan terbit', $totals['count']);
            $metrics[] = new ReportMetric('Tagihan dibatalkan', $voided);
            $notes[] = 'Pendapatan = total tagihan final (terbit, tidak dibatalkan) menurut tanggal terbit. Piutang baru = sisa tagihan yang terbit dalam rentang dan belum lunas.';
        }
        $metrics[] = new ReportMetric('Jumlah kunjungan', $visits);
        $metrics[] = new ReportMetric('Pasien baru', $newPatients);

        $tables = [$this->table(
            'Penerimaan per metode bayar',
            [
                new ReportColumn('method', 'Metode bayar'),
                new ReportColumn('count', 'Transaksi', ColumnKind::Integer),
                new ReportColumn('pay', 'Pembayaran', ColumnKind::Money),
                new ReportColumn('dp', 'Uang muka', ColumnKind::Money),
                new ReportColumn('refund', 'Refund', ColumnKind::Money),
                new ReportColumn('net', 'Bersih', ColumnKind::Money),
            ],
            $methodRows,
            $methodRows === [] ? null : $methodTotals,
            'Belum ada penerimaan pada rentang ini.',
        )];

        $tables[] = $this->topProcedures($query);

        return new ReportResult($this->key(), $f->range, $metrics, $tables, $notes);
    }

    private function topProcedures(ReportQuery $query): ReportTable
    {
        $f = $query->filters;
        $rows = $this->clinical->procedures($f->range, $f->doctorId)
            ->groupBy('ep.procedure_code', 'ep.procedure_name')
            ->orderByRaw('SUM(ep.quantity) DESC')->orderBy('ep.procedure_code')
            ->limit(self::TOP_PROCEDURES)
            ->selectRaw('ep.procedure_code as code, ep.procedure_name as name, SUM(ep.quantity) as qty, SUM(ep.quantity * ep.unit_price_amount) as value')
            ->get();

        $list = [];
        foreach ($rows as $r) {
            $row = ['code' => (string) $r->code, 'name' => (string) $r->name, 'qty' => (int) $r->qty];
            if (! $query->isOwn()) {
                $row['value'] = (int) $r->value;
            }
            $list[] = $row;
        }

        $columns = [new ReportColumn('code', 'Kode'), new ReportColumn('name', 'Tindakan'), new ReportColumn('qty', 'Jumlah', ColumnKind::Integer)];
        if (! $query->isOwn()) {
            $columns[] = new ReportColumn('value', 'Nilai', ColumnKind::Money);
        }

        return $this->table(
            'Tindakan terbanyak',
            $columns,
            $list,
            null,
            'Belum ada tindakan pada rentang ini.',
        );
    }
}
