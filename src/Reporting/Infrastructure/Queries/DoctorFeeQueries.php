<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Queries;

use Src\Reporting\Application\DoctorFeeStatementSource;
use Src\Reporting\Domain\DateRange;
use Src\Reporting\Domain\DoctorFeeScheme;
use Src\Reporting\Domain\MonthlyFee;

/**
 * Jasa dokter per bulan untuk laporan LAP-03 dan slip (P2-09). Dasar: nilai item tindakan pada tagihan final yang terbit dalam
 * rentang (setelah diskon item), dikelompokkan per dokter, bulan terbit, dan kode tindakan; potongan memakai biaya lab pesanan
 * dokter yang diterima pada bulan itu. Skema dibaca dari master dokter. Hanya membaca.
 */
final readonly class DoctorFeeQueries implements DoctorFeeStatementSource
{
    public function __construct(private FinanceQueries $finance, private ReportSource $source) {}

    /**
     * @return array<string, array{doctor: string, scheme: DoctorFeeScheme, months: list<MonthlyFee>}> id dokter => jasa per bulan
     */
    public function statements(DateRange $range, ?string $doctorId = null, ?string $methodId = null): array
    {
        $offset = $this->source->offset($range);
        $production = $this->finance->finalInvoices($range, $doctorId, $methodId)
            ->join('invoice_items as ii', function ($join): void {
                $join->on('ii.invoice_id', '=', 'i.id')->where('ii.source_type', '=', 'procedure');
            })
            ->whereNull('ii.deleted_at')
            ->join('encounter_procedures as ep', 'ep.id', '=', 'ii.source_id')
            ->groupBy('e.doctor_id', 'month', 'ep.procedure_code', 'ep.procedure_name')
            ->orderBy('ep.procedure_code')
            ->selectRaw(
                'e.doctor_id as doctor_id, '.LocalTime::INVOICE_ISSUED_MONTH.' as month, ep.procedure_code as code, ep.procedure_name as name, COALESCE(SUM(ii.quantity), 0) as qty, COALESCE(SUM(ii.total_amount), 0) as base',
                [$offset],
            )
            ->get();

        $labQuery = $this->source->from('lab_orders', 'lo')->where('lo.status', 'received')->whereBetween('lo.received_date', [$range->from, $range->to]);
        if ($doctorId !== null) {
            $labQuery->where('lo.doctor_id', $doctorId);
        }
        $lab = $labQuery->groupBy('lo.doctor_id', 'month')
            ->selectRaw("lo.doctor_id as doctor_id, DATE_FORMAT(lo.received_date, '%Y-%m') as month, COALESCE(SUM(lo.lab_cost_amount), 0) as cost")
            ->get();

        /** @var array<string, array<string, list<array{code: string, name: string, qty: int, base: int}>>> $procedures */
        $procedures = [];
        foreach ($production as $r) {
            $procedures[(string) $r->doctor_id][(string) $r->month][] = ['code' => (string) $r->code, 'name' => (string) $r->name, 'qty' => (int) $r->qty, 'base' => (int) $r->base];
        }
        /** @var array<string, array<string, int>> $labCosts */
        $labCosts = [];
        foreach ($lab as $r) {
            $labCosts[(string) $r->doctor_id][(string) $r->month] = (int) $r->cost;
        }

        $doctorIds = array_values(array_unique([...array_keys($procedures), ...array_keys($labCosts)]));
        $names = $this->source->names('doctors', $doctorIds);
        $schemes = $this->schemes($doctorIds);

        $result = [];
        foreach ($doctorIds as $id) {
            $scheme = DoctorFeeScheme::fromArray($schemes[$id] ?? null);
            $months = array_unique([...array_keys($procedures[$id] ?? []), ...array_keys($labCosts[$id] ?? [])]);
            sort($months);
            $result[$id] = [
                'doctor' => $names[$id] ?? '(dokter tidak dikenal)',
                'scheme' => $scheme,
                'months' => array_map(fn (string $month) => $scheme->month($month, $procedures[$id][$month] ?? [], $labCosts[$id][$month] ?? 0), $months),
            ];
        }

        return $result;
    }

    /**
     * @param  list<string>  $doctorIds
     * @return array<string, array<mixed>|null>
     */
    private function schemes(array $doctorIds): array
    {
        if ($doctorIds === []) {
            return [];
        }
        $schemes = [];
        foreach ($this->source->from('doctors', 'd', false, false)->whereIn('d.id', $doctorIds)->get(['d.id', 'd.fee_scheme']) as $row) {
            $decoded = is_string($row->fee_scheme) ? json_decode($row->fee_scheme, true) : null;
            $schemes[(string) $row->id] = is_array($decoded) ? $decoded : null;
        }

        return $schemes;
    }
}
