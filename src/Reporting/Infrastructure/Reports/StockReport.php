<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Reports;

use DateTimeImmutable;
use Src\Reporting\Application\ReportQuery;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportColumn;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportMetric;
use Src\Reporting\Domain\ReportResult;
use Src\Reporting\Domain\ReportTable;
use Src\Reporting\Infrastructure\Queries\ReportSource;

/**
 * LAP-07: stok saat ini, di bawah minimum, hampir kedaluwarsa, dan pergerakan stok. Rentang tanggal hanya memengaruhi
 * pergerakan stok; tiga bagian lain adalah keadaan saat ini.
 */
final readonly class StockReport extends AbstractReport
{
    /** Obat dengan batch kedaluwarsa dalam jumlah hari ini (OBT-06) dianggap hampir kedaluwarsa. */
    public const EXPIRY_WINDOW_DAYS = 30;

    public function __construct(private ReportSource $source) {}

    public function key(): ReportKey
    {
        return ReportKey::Stock;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $f = $query->filters;
        $balances = $this->balances();
        $low = array_values(array_filter($balances, fn (array $b) => $b['quantity'] < $b['min_stock']));
        $expiring = $this->expiring($balances);

        $current = array_map(fn (array $b) => ['name' => $b['name'], 'unit' => $b['unit'], 'quantity' => $b['quantity'], 'min_stock' => $b['min_stock'], 'status' => $this->status($b)], $balances);
        $lowRows = array_map(fn (array $b) => ['name' => $b['name'], 'unit' => $b['unit'], 'quantity' => $b['quantity'], 'min_stock' => $b['min_stock'], 'shortage' => $b['min_stock'] - $b['quantity']], $low);

        $tables = [
            $this->table(
                'Stok saat ini',
                [new ReportColumn('name', 'Obat atau bahan'), new ReportColumn('unit', 'Satuan'), new ReportColumn('quantity', 'Stok', ColumnKind::Integer), new ReportColumn('min_stock', 'Minimum', ColumnKind::Integer), new ReportColumn('status', 'Status')],
                $current,
                null,
                'Belum ada obat atau bahan.',
            ),
            $this->table(
                'Di bawah stok minimum',
                [new ReportColumn('name', 'Obat atau bahan'), new ReportColumn('unit', 'Satuan'), new ReportColumn('quantity', 'Stok', ColumnKind::Integer), new ReportColumn('min_stock', 'Minimum', ColumnKind::Integer), new ReportColumn('shortage', 'Kekurangan', ColumnKind::Integer)],
                $lowRows,
                null,
                'Semua stok di atas minimum.',
            ),
            $this->table(
                'Hampir kedaluwarsa atau sudah kedaluwarsa',
                [new ReportColumn('name', 'Obat atau bahan'), new ReportColumn('batch', 'Batch'), new ReportColumn('expiry', 'Kedaluwarsa', ColumnKind::Date), new ReportColumn('days_left', 'Sisa hari', ColumnKind::Integer), new ReportColumn('received', 'Diterima', ColumnKind::Integer), new ReportColumn('quantity', 'Stok kini', ColumnKind::Integer)],
                $expiring,
                null,
                'Tidak ada batch yang mendekati kedaluwarsa.',
            ),
            $this->movementSummary($query),
            $this->movementDetail($query),
        ];

        return new ReportResult($this->key(), $f->range, [
            new ReportMetric('Jenis obat dan bahan', count($balances)),
            new ReportMetric('Di bawah minimum', count($low)),
            new ReportMetric('Stok habis', count(array_filter($balances, fn (array $b) => $b['quantity'] === 0))),
            new ReportMetric('Batch hampir kedaluwarsa', count($expiring)),
        ], $tables, [
            'Di bawah minimum = stok kurang dari minimum yang diatur pada obat (hanya obat yang minimumnya lebih dari nol).',
            'Hampir kedaluwarsa = batch yang tercatat saat stok masuk dengan tanggal kedaluwarsa dalam '.self::EXPIRY_WINDOW_DAYS.' hari atau sudah lewat, pada obat yang stoknya masih ada. Sisa per batch tidak dilacak; kolom Diterima adalah jumlah saat batch masuk.',
            'Rentang tanggal hanya berlaku untuk pergerakan stok.',
        ]);
    }

    /**
     * @return list<array{id: string, name: string, unit: string, quantity: int, min_stock: int}>
     */
    private function balances(): array
    {
        $rows = $this->source->from('medicines', 'm')
            ->leftJoin('stock_balances as b', 'b.medicine_id', '=', 'm.id')
            ->where(function ($q): void {
                $q->where('m.is_active', true)->orWhere('b.quantity', '>', 0);
            })
            ->orderBy('m.name')
            ->get(['m.id', 'm.name', 'm.unit', 'm.min_stock', 'b.quantity']);

        $list = [];
        foreach ($rows as $r) {
            $list[] = ['id' => (string) $r->id, 'name' => (string) $r->name, 'unit' => (string) $r->unit, 'quantity' => (int) ($r->quantity ?? 0), 'min_stock' => (int) $r->min_stock];
        }

        return $list;
    }

    /**
     * @param  array{id: string, name: string, unit: string, quantity: int, min_stock: int}  $b
     */
    private function status(array $b): string
    {
        return match (true) {
            $b['quantity'] === 0 => 'Habis',
            $b['min_stock'] > 0 && $b['quantity'] < $b['min_stock'] => 'Di bawah minimum',
            default => 'Cukup',
        };
    }

    /**
     * @param  list<array{id: string, name: string, unit: string, quantity: int, min_stock: int}>  $balances
     * @return list<array{name: string, batch: string|null, expiry: string, days_left: int, received: int, quantity: int}>
     */
    private function expiring(array $balances): array
    {
        $byId = [];
        foreach ($balances as $b) {
            if ($b['quantity'] > 0) {
                $byId[$b['id']] = $b;
            }
        }
        if ($byId === []) {
            return [];
        }
        $today = new DateTimeImmutable($this->source->today());
        $limit = $today->modify('+'.self::EXPIRY_WINDOW_DAYS.' days')->format('Y-m-d');
        $rows = $this->source->from('stock_movements', 's', false)
            ->whereIn('s.medicine_id', array_keys($byId))
            ->where('s.type', 'in')
            ->whereNotNull('s.expiry_date')
            ->where('s.expiry_date', '<=', $limit)
            ->orderBy('s.expiry_date')
            ->get(['s.medicine_id', 's.batch_no', 's.expiry_date', 's.quantity']);

        $list = [];
        foreach ($rows as $r) {
            $expiry = substr((string) $r->expiry_date, 0, 10);
            $list[] = [
                'name' => $byId[(string) $r->medicine_id]['name'], 'batch' => $r->batch_no === null ? null : (string) $r->batch_no, 'expiry' => $expiry,
                'days_left' => (int) $today->diff(new DateTimeImmutable($expiry))->format('%r%a'), 'received' => (int) $r->quantity, 'quantity' => $byId[(string) $r->medicine_id]['quantity'],
            ];
        }

        return $list;
    }

    private function movementSummary(ReportQuery $query): ReportTable
    {
        $f = $query->filters;
        $base = $this->source->from('stock_movements', 's', false)->join('medicines as m', 'm.id', '=', 's.medicine_id');
        $this->source->within($base, 's.created_at', $f->range);
        $rows = $base->groupBy('s.medicine_id', 'm.name', 'm.unit')->orderBy('m.name')
            ->selectRaw("m.name as name, m.unit as unit,
                COALESCE(SUM(CASE WHEN s.type = 'in' THEN s.quantity ELSE 0 END), 0) as in_qty,
                COALESCE(SUM(CASE WHEN s.type = 'out' THEN -s.quantity ELSE 0 END), 0) as out_qty,
                COALESCE(SUM(CASE WHEN s.type = 'adjustment' THEN s.quantity ELSE 0 END), 0) as adj_qty")
            ->get();

        $list = [];
        foreach ($rows as $r) {
            $list[] = ['name' => (string) $r->name, 'unit' => (string) $r->unit, 'in' => (int) $r->in_qty, 'out' => (int) $r->out_qty, 'adjustment' => (int) $r->adj_qty, 'net' => (int) $r->in_qty - (int) $r->out_qty + (int) $r->adj_qty];
        }

        return $this->table(
            'Pergerakan stok per obat',
            [new ReportColumn('name', 'Obat atau bahan'), new ReportColumn('unit', 'Satuan'), new ReportColumn('in', 'Masuk', ColumnKind::Integer), new ReportColumn('out', 'Keluar', ColumnKind::Integer), new ReportColumn('adjustment', 'Penyesuaian', ColumnKind::Integer), new ReportColumn('net', 'Selisih', ColumnKind::Integer)],
            $list,
            null,
            'Tidak ada pergerakan stok pada rentang ini.',
        );
    }

    private function movementDetail(ReportQuery $query): ReportTable
    {
        $f = $query->filters;
        $base = $this->source->from('stock_movements', 's', false)->join('medicines as m', 'm.id', '=', 's.medicine_id');
        $this->source->within($base, 's.created_at', $f->range);
        $rows = $base->orderByDesc('s.created_at')->orderByDesc('s.id')->limit(ReportTable::MAX_ROWS + 1)
            ->get(['s.created_at', 'm.name', 's.type', 's.quantity', 's.balance_after', 's.reason', 's.batch_no', 's.created_by']);
        $users = $this->source->names('users', array_map(fn ($r) => (string) ($r->created_by ?? ''), $rows->all()));
        $labels = ['in' => 'Masuk', 'out' => 'Keluar', 'adjustment' => 'Penyesuaian'];

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'at' => $this->source->localTime((string) $r->created_at), 'name' => (string) $r->name, 'type' => $labels[(string) $r->type] ?? (string) $r->type,
                'quantity' => (int) $r->quantity, 'balance' => (int) $r->balance_after, 'reason' => (string) ($r->reason ?? ''), 'by' => $users[(string) ($r->created_by ?? '')] ?? '-',
            ];
        }

        return $this->table(
            'Riwayat pergerakan stok',
            [new ReportColumn('at', 'Waktu', ColumnKind::DateTime), new ReportColumn('name', 'Obat atau bahan'), new ReportColumn('type', 'Jenis'), new ReportColumn('quantity', 'Jumlah', ColumnKind::Integer), new ReportColumn('balance', 'Saldo', ColumnKind::Integer), new ReportColumn('reason', 'Keterangan'), new ReportColumn('by', 'Petugas')],
            $list,
            null,
            'Tidak ada pergerakan stok pada rentang ini.',
        );
    }
}
