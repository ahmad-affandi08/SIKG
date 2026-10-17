<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Reports;

use Src\Reporting\Application\ReportQuery;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\DoctorFeeRule;
use Src\Reporting\Domain\ReportColumn;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportMetric;
use Src\Reporting\Domain\ReportResult;
use Src\Reporting\Infrastructure\Queries\FinanceQueries;
use Src\Reporting\Infrastructure\Queries\ReportSource;

/**
 * LAP-03: jasa dokter per periode menurut skema di master dokter. Dasar hitung: nilai tindakan pada tagihan final yang
 * terbit dalam rentang (setelah diskon item). Obat dan item manual tidak menghasilkan jasa. Skema dan asumsinya di Q-11 dan Q-23.
 */
final readonly class DoctorFeesReport extends AbstractReport
{
    public function __construct(private FinanceQueries $finance, private ReportSource $source) {}

    public function key(): ReportKey
    {
        return ReportKey::DoctorFees;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $f = $query->filters;
        $rows = $this->finance->finalInvoices($f->range, $f->doctorId, $f->methodId)
            ->join('invoice_items as ii', function ($join): void {
                $join->on('ii.invoice_id', '=', 'i.id')->where('ii.source_type', '=', 'procedure');
            })
            ->whereNull('ii.deleted_at')
            ->join('encounter_procedures as ep', 'ep.id', '=', 'ii.source_id')
            ->groupBy('e.doctor_id', 'ep.procedure_code', 'ep.procedure_name')
            ->orderBy('ep.procedure_code')
            ->selectRaw('e.doctor_id as doctor_id, ep.procedure_code as code, ep.procedure_name as name, COALESCE(SUM(ii.quantity), 0) as qty, COALESCE(SUM(ii.total_amount), 0) as base')
            ->get();

        $doctorIds = array_values(array_unique(array_map(fn ($r) => (string) $r->doctor_id, $rows->all())));
        $names = $this->source->names('doctors', $doctorIds);
        $schemes = $this->schemes($doctorIds);

        $detail = [];
        $perDoctor = [];
        foreach ($rows as $r) {
            $doctorId = (string) $r->doctor_id;
            $rule = DoctorFeeRule::forProcedure($schemes[$doctorId] ?? null, (string) $r->code);
            $qty = (int) $r->qty;
            $base = (int) $r->base;
            $fee = $rule->feeFor($base, $qty)->amount;
            $doctor = $names[$doctorId] ?? '(dokter tidak dikenal)';

            $detail[] = ['doctor' => $doctor, 'code' => (string) $r->code, 'name' => (string) $r->name, 'qty' => $qty, 'base' => $base, 'rule' => $rule->label(), 'fee' => $fee];
            $perDoctor[$doctorId] ??= ['doctor' => $doctor, 'qty' => 0, 'base' => 0, 'fee' => 0];
            $perDoctor[$doctorId]['qty'] += $qty;
            $perDoctor[$doctorId]['base'] += $base;
            $perDoctor[$doctorId]['fee'] += $fee;
        }
        usort($detail, fn (array $a, array $b) => [$a['doctor'], $a['code']] <=> [$b['doctor'], $b['code']]);
        $summary = array_values($perDoctor);
        usort($summary, fn (array $a, array $b) => [$b['fee'], $a['doctor']] <=> [$a['fee'], $b['doctor']]);

        $totalFee = array_sum(array_column($summary, 'fee'));
        $totalBase = array_sum(array_column($summary, 'base'));

        return new ReportResult($this->key(), $f->range, [
            new ReportMetric('Total jasa dokter', $totalFee, ColumnKind::Money),
            new ReportMetric('Nilai tindakan (dasar hitung)', $totalBase, ColumnKind::Money),
            new ReportMetric('Dokter dengan jasa', count($summary)),
        ], [
            $this->table(
                'Jasa per dokter',
                [new ReportColumn('doctor', 'Dokter'), new ReportColumn('qty', 'Tindakan', ColumnKind::Integer), new ReportColumn('base', 'Nilai tindakan', ColumnKind::Money), new ReportColumn('fee', 'Jasa dokter', ColumnKind::Money)],
                $summary,
                $summary === [] ? null : ['doctor' => 'Total', 'qty' => array_sum(array_column($summary, 'qty')), 'base' => $totalBase, 'fee' => $totalFee],
                'Belum ada tindakan pada tagihan final dalam rentang ini.',
            ),
            $this->table(
                'Rincian per tindakan',
                [
                    new ReportColumn('doctor', 'Dokter'), new ReportColumn('code', 'Kode'), new ReportColumn('name', 'Tindakan'),
                    new ReportColumn('qty', 'Jumlah', ColumnKind::Integer), new ReportColumn('base', 'Nilai tindakan', ColumnKind::Money),
                    new ReportColumn('rule', 'Aturan jasa'), new ReportColumn('fee', 'Jasa dokter', ColumnKind::Money),
                ],
                $detail,
                null,
                'Belum ada tindakan pada tagihan final dalam rentang ini.',
            ),
        ], [
            'Dasar hitung adalah nilai item tindakan pada tagihan final yang terbit dalam rentang, setelah diskon item; diskon tagihan keseluruhan tidak dibagi ke item.',
            'Aturan jasa dibaca dari skema di master dokter: persentase dari nilai tindakan atau jumlah tetap per tindakan, dengan pengecualian per kode tindakan. Dokter tanpa skema berjasa nol.',
            'Jasa dihitung saat tagihan terbit, tanpa melihat apakah tagihan sudah lunas. Jasa yang dibatalkan ikut hilang karena tagihan batal tidak dihitung.',
        ]);
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
