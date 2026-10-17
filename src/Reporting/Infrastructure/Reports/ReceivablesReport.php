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
use Src\Reporting\Infrastructure\Queries\FinanceQueries;
use Src\Reporting\Infrastructure\Queries\LocalTime;
use Src\Reporting\Infrastructure\Queries\ReportSource;

/**
 * LAP-06: tagihan final yang belum lunas (menurut tanggal terbit dalam rentang), umur piutang terhadap hari ini, dan uang
 * muka yang belum terpakai (uang muka pada tagihan yang belum diterbitkan).
 */
final readonly class ReceivablesReport extends AbstractReport
{
    /** Batas atas umur (hari) tiap kelompok; kelompok terakhir tanpa batas. */
    private const BUCKETS = [[0, 30, '0-30 hari'], [31, 60, '31-60 hari'], [61, 90, '61-90 hari'], [91, null, 'Lebih dari 90 hari']];

    public function __construct(private FinanceQueries $finance, private ReportSource $source) {}

    public function key(): ReportKey
    {
        return ReportKey::Receivables;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $f = $query->filters;
        $today = $this->source->today();
        $ageBindings = [$today, $this->source->offset($f->range)];

        $open = $this->finance->finalInvoices($f->range, $f->doctorId, $f->methodId)->whereColumn('i.paid_amount', '<', 'i.total_amount');

        $aging = [];
        foreach (self::BUCKETS as [$min, $max, $label]) {
            $bucket = (clone $open)->whereRaw(LocalTime::INVOICE_AGE_DAYS.' >= ?', [...$ageBindings, $min]);
            if ($max !== null) {
                $bucket->whereRaw(LocalTime::INVOICE_AGE_DAYS.' <= ?', [...$ageBindings, $max]);
            }
            $row = $bucket->selectRaw('COUNT(*) as n, COALESCE(SUM(i.total_amount - i.paid_amount), 0) as balance')->first();
            $aging[] = ['bucket' => $label, 'count' => (int) ($row->n ?? 0), 'balance' => (int) ($row->balance ?? 0)];
        }
        $count = array_sum(array_column($aging, 'count'));
        $balance = array_sum(array_column($aging, 'balance'));

        $deposits = $this->depositRows($query);
        $depositTotal = array_sum(array_column($deposits, 'amount'));

        return new ReportResult($this->key(), $f->range, [
            new ReportMetric('Tagihan belum lunas', $count),
            new ReportMetric('Total piutang', $balance, ColumnKind::Money),
            new ReportMetric('Uang muka belum terpakai', $depositTotal, ColumnKind::Money),
            new ReportMetric('Tagihan dengan uang muka', count($deposits)),
        ], [
            $this->table(
                'Umur piutang',
                [new ReportColumn('bucket', 'Umur'), new ReportColumn('count', 'Tagihan', ColumnKind::Integer), new ReportColumn('balance', 'Sisa tagihan', ColumnKind::Money)],
                $aging,
                ['bucket' => 'Total', 'count' => $count, 'balance' => $balance],
            ),
            $this->openInvoices($query, $ageBindings),
            $this->table(
                'Uang muka belum terpakai',
                [new ReportColumn('paid_at', 'Tanggal', ColumnKind::DateTime), new ReportColumn('patient', 'Pasien'), new ReportColumn('method', 'Metode bayar'), new ReportColumn('amount', 'Jumlah', ColumnKind::Money)],
                $deposits,
                $deposits === [] ? null : ['paid_at' => null, 'patient' => 'Total', 'method' => null, 'amount' => $depositTotal],
                'Tidak ada uang muka yang menunggu tagihan.',
            ),
        ], [
            'Piutang = sisa tagihan final (terbit, tidak dibatalkan) yang belum lunas. Umur dihitung dari tanggal terbit sampai hari ini, bukan sampai akhir rentang.',
            'Rentang tanggal memilih tagihan menurut tanggal terbit. Uang muka belum terpakai adalah uang muka pada tagihan yang belum diterbitkan; setelah tagihan terbit, uang muka dihitung sebagai pembayaran.',
        ]);
    }

    /**
     * @param  array{0: string, 1: string}  $ageBindings  hari ini lokal dan selisih zona
     */
    private function openInvoices(ReportQuery $query, array $ageBindings): ReportTable
    {
        $f = $query->filters;
        $rows = $this->finance->finalInvoices($f->range, $f->doctorId, $f->methodId)
            ->whereColumn('i.paid_amount', '<', 'i.total_amount')
            ->join('patients as pt', 'pt.id', '=', 'i.patient_id')
            ->orderByRaw('(i.total_amount - i.paid_amount) DESC')->orderBy('i.issued_at')
            ->limit(ReportTable::MAX_ROWS + 1)
            ->selectRaw(
                'i.number as number, i.issued_at as issued_at, pt.name as patient, pt.medical_record_no as mr, i.status as status,
                i.total_amount as total, i.paid_amount as paid, (i.total_amount - i.paid_amount) as balance, '.LocalTime::INVOICE_AGE_DAYS.' as age',
                $ageBindings,
            )
            ->get();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'number' => (string) $r->number, 'issued_at' => $this->source->localTime((string) $r->issued_at), 'patient' => (string) $r->patient.' ('.(string) $r->mr.')',
                'status' => $r->status === 'partial' ? 'Dibayar sebagian' : 'Belum dibayar', 'total' => (int) $r->total, 'paid' => (int) $r->paid, 'balance' => (int) $r->balance, 'age' => (int) $r->age,
            ];
        }

        return $this->table(
            'Tagihan belum lunas',
            [
                new ReportColumn('number', 'Nomor'), new ReportColumn('issued_at', 'Terbit', ColumnKind::DateTime), new ReportColumn('patient', 'Pasien'), new ReportColumn('status', 'Status'),
                new ReportColumn('total', 'Total', ColumnKind::Money), new ReportColumn('paid', 'Dibayar', ColumnKind::Money), new ReportColumn('balance', 'Sisa', ColumnKind::Money), new ReportColumn('age', 'Umur (hari)', ColumnKind::Integer),
            ],
            $list,
            null,
            'Tidak ada tagihan yang belum lunas pada rentang ini.',
        );
    }

    /**
     * @return list<array{paid_at: string|null, patient: string, method: string, amount: int}>
     */
    private function depositRows(ReportQuery $query): array
    {
        $f = $query->filters;
        $rows = $this->source->from('payments', 'p', false)
            ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->join('encounters as e', 'e.id', '=', 'i.encounter_id')
            ->join('patients as pt', 'pt.id', '=', 'i.patient_id')
            ->join('payment_methods as m', 'm.id', '=', 'p.payment_method_id')
            ->whereNull('i.deleted_at')
            ->where('i.lifecycle', 'draft')
            ->where('p.type', 'dp');
        if ($f->doctorId !== null) {
            $rows->where('e.doctor_id', $f->doctorId);
        }
        if ($f->methodId !== null) {
            $rows->where('p.payment_method_id', $f->methodId);
        }
        $result = $rows->orderBy('p.paid_at')->limit(ReportTable::MAX_ROWS + 1)
            ->get(['p.paid_at', 'pt.name as patient', 'pt.medical_record_no as mr', 'm.name as method', 'p.amount']);

        $list = [];
        foreach ($result as $r) {
            $list[] = ['paid_at' => $this->source->localTime((string) $r->paid_at), 'patient' => (string) $r->patient.' ('.(string) $r->mr.')', 'method' => (string) $r->method, 'amount' => (int) $r->amount];
        }

        return $list;
    }
}
