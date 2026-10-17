<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Queries;

use Illuminate\Database\Query\Builder;
use Src\Reporting\Domain\DateRange;

/**
 * Kueri keuangan yang dipakai beberapa laporan dan dasbor. Dua dasar yang tidak dicampur:
 * pendapatan = tagihan final (terbit, bukan draft dan bukan batal) menurut tanggal terbit;
 * penerimaan = uang masuk bersih (pembayaran dan uang muka dikurangi refund) menurut tanggal bayar.
 * Tagihan batal dilaporkan terpisah. Semua nilai bilangan bulat rupiah (M4).
 */
final readonly class FinanceQueries
{
    public function __construct(private ReportSource $source) {}

    /**
     * Tagihan final yang terbit dalam rentang. Kolom dokter berasal dari kunjungan.
     */
    public function finalInvoices(DateRange $range, ?string $doctorId = null, ?string $methodId = null): Builder
    {
        $query = $this->source->from('invoices', 'i')
            ->join('encounters as e', 'e.id', '=', 'i.encounter_id')
            ->where('i.lifecycle', 'issued');
        $this->source->within($query, 'i.issued_at', $range);
        if ($doctorId !== null) {
            $query->where('e.doctor_id', $doctorId);
        }
        if ($methodId !== null) {
            $query->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('payments as pf')->whereColumn('pf.invoice_id', 'i.id')->where('pf.payment_method_id', $methodId));
        }

        return $query;
    }

    /**
     * Tagihan yang dibatalkan dalam rentang (menurut waktu batal).
     */
    public function voidedInvoices(DateRange $range, ?string $doctorId = null): Builder
    {
        $query = $this->source->from('invoices', 'i')
            ->join('encounters as e', 'e.id', '=', 'i.encounter_id')
            ->where('i.lifecycle', 'void');
        $this->source->within($query, 'i.voided_at', $range);
        if ($doctorId !== null) {
            $query->where('e.doctor_id', $doctorId);
        }

        return $query;
    }

    /**
     * Transaksi pembayaran dalam rentang menurut waktu bayar.
     *
     * @param  string|null  $receivedBy  hanya yang dicatat pengguna ini (cakupan "sendiri" Kasir)
     */
    public function payments(DateRange $range, ?string $doctorId = null, ?string $methodId = null, ?string $receivedBy = null): Builder
    {
        $query = $this->source->from('payments', 'p', false)
            ->join('payment_methods as m', 'm.id', '=', 'p.payment_method_id');
        $this->source->within($query, 'p.paid_at', $range);
        if ($doctorId !== null) {
            $query->join('invoices as ip', 'ip.id', '=', 'p.invoice_id')
                ->join('encounters as ep', 'ep.id', '=', 'ip.encounter_id')
                ->where('ep.doctor_id', $doctorId);
        }
        if ($methodId !== null) {
            $query->where('p.payment_method_id', $methodId);
        }
        if ($receivedBy !== null) {
            $query->where('p.received_by', $receivedBy);
        }

        return $query;
    }

    /**
     * Penerimaan per metode bayar.
     *
     * @return list<array{method_id: string, method: string, type: string, pay: int, dp: int, refund: int, net: int, count: int}>
     */
    public function receiptsByMethod(DateRange $range, ?string $doctorId = null, ?string $methodId = null, ?string $receivedBy = null): array
    {
        $rows = $this->payments($range, $doctorId, $methodId, $receivedBy)
            ->groupBy('m.id', 'm.name', 'm.type')
            ->orderBy('m.name')
            ->selectRaw("m.id as method_id, m.name as method, m.type as type,
                COALESCE(SUM(CASE WHEN p.type = 'pay' THEN p.amount ELSE 0 END), 0) as pay,
                COALESCE(SUM(CASE WHEN p.type = 'dp' THEN p.amount ELSE 0 END), 0) as dp,
                COALESCE(SUM(CASE WHEN p.type = 'refund' THEN p.amount ELSE 0 END), 0) as refund,
                COUNT(*) as n")
            ->get();

        $list = [];
        foreach ($rows as $row) {
            $pay = (int) $row->pay;
            $dp = (int) $row->dp;
            $refund = (int) $row->refund;
            $list[] = [
                'method_id' => (string) $row->method_id, 'method' => (string) $row->method, 'type' => (string) $row->type,
                'pay' => $pay, 'dp' => $dp, 'refund' => $refund, 'net' => $pay + $dp - $refund, 'count' => (int) $row->n,
            ];
        }

        return $list;
    }

    /**
     * Jumlah dan nilai tagihan final dalam rentang.
     *
     * @return array{count: int, subtotal: int, discount: int, tax: int, total: int, paid: int}
     */
    public function invoiceTotals(DateRange $range, ?string $doctorId = null, ?string $methodId = null): array
    {
        $row = $this->finalInvoices($range, $doctorId, $methodId)->selectRaw(
            'COUNT(*) as n, COALESCE(SUM(i.subtotal_amount), 0) as subtotal, COALESCE(SUM(i.discount_amount), 0) as discount,
             COALESCE(SUM(i.tax_amount), 0) as tax, COALESCE(SUM(i.total_amount), 0) as total, COALESCE(SUM(i.paid_amount), 0) as paid',
        )->first();

        return [
            'count' => (int) ($row->n ?? 0), 'subtotal' => (int) ($row->subtotal ?? 0), 'discount' => (int) ($row->discount ?? 0),
            'tax' => (int) ($row->tax ?? 0), 'total' => (int) ($row->total ?? 0), 'paid' => (int) ($row->paid ?? 0),
        ];
    }

    /**
     * Total pendapatan (tagihan final) per hari lokal, kunci YYYY-MM-DD.
     *
     * @return array<string, int>
     */
    public function revenueByDay(DateRange $range): array
    {
        $offset = $this->source->offset($range);
        $rows = $this->finalInvoices($range)
            ->groupBy('d')
            ->selectRaw(LocalTime::INVOICE_ISSUED_DAY.' as d, COALESCE(SUM(i.total_amount), 0) as total', [$offset])
            ->get();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->d] = (int) $row->total;
        }

        return $map;
    }

    /**
     * Piutang berjalan: sisa tagihan final yang belum lunas.
     *
     * @return array{count: int, balance: int}
     */
    public function openReceivables(?string $olderThanUtc = null): array
    {
        $query = $this->source->from('invoices', 'i')->where('i.lifecycle', 'issued')->whereColumn('i.paid_amount', '<', 'i.total_amount');
        if ($olderThanUtc !== null) {
            $query->where('i.issued_at', '<', $olderThanUtc);
        }
        $row = $query->selectRaw('COUNT(*) as n, COALESCE(SUM(i.total_amount - i.paid_amount), 0) as balance')->first();

        return ['count' => (int) ($row->n ?? 0), 'balance' => (int) ($row->balance ?? 0)];
    }
}
