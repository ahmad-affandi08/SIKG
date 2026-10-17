<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Reports;

use Src\Reporting\Application\ReportFilters;
use Src\Reporting\Application\ReportQuery;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportColumn;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportMetric;
use Src\Reporting\Domain\ReportResult;
use Src\Reporting\Domain\ReportTable;
use Src\Reporting\Infrastructure\Queries\FinanceQueries;
use Src\Reporting\Infrastructure\Queries\LocalTime;
use Src\Reporting\Infrastructure\Queries\ReportSource;

/**
 * LAP-02: pendapatan harian atau bulanan, per dokter, per kategori tindakan, dan penerimaan per metode bayar. Pendapatan
 * hanya dari tagihan final; tagihan batal tampil terpisah.
 */
final readonly class RevenueReport extends AbstractReport
{
    /** Kategori tiap item tagihan: kategori tindakan, "Obat", atau "Item manual". */
    private const CATEGORY_SQL = "CASE ii.source_type WHEN 'procedure' THEN COALESCE(NULLIF(pr.category, ''), 'Tanpa kategori') WHEN 'medicine' THEN 'Obat' ELSE 'Item manual' END";

    public function __construct(private FinanceQueries $finance, private ReportSource $source) {}

    public function key(): ReportKey
    {
        return ReportKey::Revenue;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $f = $query->filters;
        $totals = $this->finance->invoiceTotals($f->range, $f->doctorId, $f->methodId);
        $byMethod = $this->finance->receiptsByMethod($f->range, $f->doctorId, $f->methodId);
        $received = array_sum(array_column($byMethod, 'net'));
        $voided = $this->finance->voidedInvoices($f->range, $f->doctorId)->selectRaw('COUNT(*) as n, COALESCE(SUM(i.total_amount), 0) as total')->first();

        $metrics = [
            new ReportMetric('Pendapatan (tagihan terbit)', $totals['total'], ColumnKind::Money),
            new ReportMetric('Penerimaan bersih', $received, ColumnKind::Money),
            new ReportMetric('Tagihan terbit', $totals['count']),
            new ReportMetric('Diskon', $totals['discount'], ColumnKind::Money),
            new ReportMetric('Pajak', $totals['tax'], ColumnKind::Money),
            new ReportMetric('Tagihan dibatalkan', (int) ($voided->n ?? 0)),
        ];

        $notes = [
            'Pendapatan = total tagihan final (terbit, tidak dibatalkan) menurut tanggal terbit. Penerimaan = uang masuk bersih menurut tanggal bayar; keduanya dapat berbeda pada periode yang sama.',
            'Per kategori tindakan memakai nilai item setelah diskon item; diskon tagihan dan pajak ditampilkan sebagai baris tersendiri agar jumlahnya sama dengan pendapatan.',
        ];
        if ($f->methodId !== null) {
            $notes[] = 'Filter metode bayar pada bagian pendapatan hanya mengambil tagihan yang pernah dibayar dengan metode itu.';
        }

        return new ReportResult($this->key(), $f->range, $metrics, [
            $this->periodTable($f),
            $this->doctorTable($f),
            $this->categoryTable($f, $totals),
            $this->methodTable($byMethod),
            $this->voidTable($query),
        ], $notes);
    }

    private function periodTable(ReportFilters $f): ReportTable
    {
        $monthly = $f->grain === ReportFilters::GRAIN_MONTH;
        $offset = $this->source->offset($f->range);
        $rows = $this->finance->finalInvoices($f->range, $f->doctorId, $f->methodId)
            ->groupBy('period')->orderBy('period')
            ->selectRaw(
                ($monthly ? LocalTime::INVOICE_ISSUED_MONTH : LocalTime::INVOICE_ISSUED_DAY).' as period, COUNT(*) as n, COALESCE(SUM(i.subtotal_amount), 0) as subtotal,
                COALESCE(SUM(i.discount_amount), 0) as discount, COALESCE(SUM(i.tax_amount), 0) as tax, COALESCE(SUM(i.total_amount), 0) as total',
                [$offset],
            )
            ->get();

        $list = [];
        $sum = ['period' => 'Total', 'count' => 0, 'subtotal' => 0, 'discount' => 0, 'tax' => 0, 'total' => 0];
        foreach ($rows as $r) {
            $row = ['period' => (string) $r->period, 'count' => (int) $r->n, 'subtotal' => (int) $r->subtotal, 'discount' => (int) $r->discount, 'tax' => (int) $r->tax, 'total' => (int) $r->total];
            $list[] = $row;
            foreach (['count', 'subtotal', 'discount', 'tax', 'total'] as $k) {
                $sum[$k] += $row[$k];
            }
        }

        return $this->table(
            $monthly ? 'Pendapatan per bulan' : 'Pendapatan per hari',
            [
                new ReportColumn('period', $monthly ? 'Bulan' : 'Tanggal', $monthly ? ColumnKind::Text : ColumnKind::Date),
                new ReportColumn('count', 'Tagihan', ColumnKind::Integer),
                new ReportColumn('subtotal', 'Subtotal', ColumnKind::Money),
                new ReportColumn('discount', 'Diskon', ColumnKind::Money),
                new ReportColumn('tax', 'Pajak', ColumnKind::Money),
                new ReportColumn('total', 'Pendapatan', ColumnKind::Money),
            ],
            $list,
            $list === [] ? null : $sum,
            'Belum ada tagihan terbit pada rentang ini.',
        );
    }

    private function doctorTable(ReportFilters $f): ReportTable
    {
        $rows = $this->finance->finalInvoices($f->range, $f->doctorId, $f->methodId)
            ->groupBy('e.doctor_id')
            ->selectRaw('e.doctor_id as doctor_id, COUNT(*) as n, COALESCE(SUM(i.total_amount), 0) as total')
            ->get();
        $names = $this->source->names('doctors', array_map(fn ($r) => (string) $r->doctor_id, $rows->all()));

        $list = [];
        $sum = ['doctor' => 'Total', 'count' => 0, 'total' => 0];
        foreach ($rows as $r) {
            $list[] = ['doctor' => $names[(string) $r->doctor_id] ?? '(dokter tidak dikenal)', 'count' => (int) $r->n, 'total' => (int) $r->total];
            $sum['count'] += (int) $r->n;
            $sum['total'] += (int) $r->total;
        }
        usort($list, fn (array $a, array $b) => [$b['total'], $a['doctor']] <=> [$a['total'], $b['doctor']]);

        return $this->table(
            'Pendapatan per dokter',
            [new ReportColumn('doctor', 'Dokter'), new ReportColumn('count', 'Tagihan', ColumnKind::Integer), new ReportColumn('total', 'Pendapatan', ColumnKind::Money)],
            $list,
            $list === [] ? null : $sum,
            'Belum ada tagihan terbit pada rentang ini.',
        );
    }

    /**
     * @param  array{count: int, subtotal: int, discount: int, tax: int, total: int, paid: int}  $totals
     */
    private function categoryTable(ReportFilters $f, array $totals): ReportTable
    {
        $rows = $this->finance->finalInvoices($f->range, $f->doctorId, $f->methodId)
            ->join('invoice_items as ii', 'ii.invoice_id', '=', 'i.id')
            ->whereNull('ii.deleted_at')
            ->leftJoin('encounter_procedures as ep', function ($join): void {
                $join->on('ep.id', '=', 'ii.source_id')->where('ii.source_type', '=', 'procedure');
            })
            ->leftJoin('procedures as pr', 'pr.id', '=', 'ep.procedure_id')
            ->groupByRaw(self::CATEGORY_SQL)->orderByRaw(self::CATEGORY_SQL)
            ->selectRaw(self::CATEGORY_SQL.' as category, COALESCE(SUM(ii.quantity), 0) as qty, COALESCE(SUM(ii.total_amount), 0) as total')
            ->get();

        $list = [];
        foreach ($rows as $r) {
            $list[] = ['category' => (string) $r->category, 'qty' => (int) $r->qty, 'total' => (int) $r->total];
        }
        if ($totals['discount'] > 0) {
            $list[] = ['category' => 'Diskon tagihan', 'qty' => null, 'total' => -$totals['discount']];
        }
        if ($totals['tax'] > 0) {
            $list[] = ['category' => 'Pajak', 'qty' => null, 'total' => $totals['tax']];
        }

        return $this->table(
            'Pendapatan per kategori tindakan',
            [new ReportColumn('category', 'Kategori'), new ReportColumn('qty', 'Jumlah', ColumnKind::Integer), new ReportColumn('total', 'Nilai', ColumnKind::Money)],
            $list,
            $list === [] ? null : ['category' => 'Total', 'qty' => null, 'total' => array_sum(array_column($list, 'total'))],
            'Belum ada tagihan terbit pada rentang ini.',
        );
    }

    /**
     * @param  list<array{method_id: string, method: string, type: string, pay: int, dp: int, refund: int, net: int, count: int}>  $byMethod
     */
    private function methodTable(array $byMethod): ReportTable
    {
        $list = [];
        foreach ($byMethod as $m) {
            $list[] = ['method' => $m['method'], 'pay' => $m['pay'], 'dp' => $m['dp'], 'refund' => $m['refund'], 'net' => $m['net']];
        }

        return $this->table(
            'Penerimaan per metode bayar',
            [
                new ReportColumn('method', 'Metode bayar'),
                new ReportColumn('pay', 'Pembayaran', ColumnKind::Money),
                new ReportColumn('dp', 'Uang muka', ColumnKind::Money),
                new ReportColumn('refund', 'Refund', ColumnKind::Money),
                new ReportColumn('net', 'Bersih', ColumnKind::Money),
            ],
            $list,
            $list === [] ? null : ['method' => 'Total', 'pay' => array_sum(array_column($list, 'pay')), 'dp' => array_sum(array_column($list, 'dp')), 'refund' => array_sum(array_column($list, 'refund')), 'net' => array_sum(array_column($list, 'net'))],
            'Belum ada penerimaan pada rentang ini.',
        );
    }

    private function voidTable(ReportQuery $query): ReportTable
    {
        $f = $query->filters;
        $rows = $this->finance->voidedInvoices($f->range, $f->doctorId)
            ->orderBy('i.voided_at')->limit(ReportTable::MAX_ROWS + 1)
            ->get(['i.number', 'i.voided_at', 'i.total_amount', 'i.void_reason']);

        $list = [];
        foreach ($rows as $r) {
            $list[] = ['number' => $r->number === null ? 'Draft' : (string) $r->number, 'voided_at' => $this->source->localTime((string) $r->voided_at), 'total' => (int) $r->total_amount, 'reason' => (string) ($r->void_reason ?? '')];
        }

        return $this->table(
            'Tagihan dibatalkan (tidak masuk pendapatan)',
            [new ReportColumn('number', 'Nomor'), new ReportColumn('voided_at', 'Dibatalkan', ColumnKind::DateTime), new ReportColumn('total', 'Total', ColumnKind::Money), new ReportColumn('reason', 'Alasan')],
            $list,
            null,
            'Tidak ada tagihan dibatalkan pada rentang ini.',
        );
    }
}
