<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Reports;

use Src\Reporting\Application\ReportLookups;
use Src\Reporting\Application\ReportQuery;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportColumn;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportMetric;
use Src\Reporting\Domain\ReportResult;
use Src\Reporting\Domain\ReportTable;
use Src\Reporting\Infrastructure\Queries\ClinicalQueries;
use Src\Reporting\Infrastructure\Queries\ReportSource;
use Src\Shared\Domain\Money;

/**
 * LAP-05: frekuensi dan nilai per jenis tindakan. Dokter hanya melihat tindakan dari kunjungan yang ia tangani.
 * Nilai = jumlah dikali tarif saat tindakan dicatat (sebelum diskon tagihan).
 */
final readonly class ProceduresReport extends AbstractReport
{
    public function __construct(private ClinicalQueries $clinical, private ReportSource $source, private ReportLookups $lookups) {}

    public function key(): ReportKey
    {
        return ReportKey::Procedures;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $f = $query->filters;
        $doctorId = $this->doctorScope($query, $this->lookups);

        $rows = $this->clinical->procedures($f->range, $doctorId)
            ->leftJoin('procedures as pr', 'pr.id', '=', 'ep.procedure_id')
            ->groupBy('ep.procedure_code', 'ep.procedure_name', 'pr.category')
            ->orderByRaw('SUM(ep.quantity) DESC')->orderBy('ep.procedure_code')
            ->selectRaw('ep.procedure_code as code, ep.procedure_name as name, pr.category as category, SUM(ep.quantity) as qty, COUNT(DISTINCT ep.encounter_id) as visits, SUM(ep.quantity * ep.unit_price_amount) as value')
            ->get();

        $list = [];
        $sum = ['code' => 'Total', 'name' => null, 'category' => null, 'qty' => 0, 'visits' => null, 'value' => 0, 'average' => null];
        foreach ($rows as $r) {
            $qty = (int) $r->qty;
            $value = (int) $r->value;
            $list[] = [
                'code' => (string) $r->code, 'name' => (string) $r->name, 'category' => $r->category === null || $r->category === '' ? 'Tanpa kategori' : (string) $r->category,
                'qty' => $qty, 'visits' => (int) $r->visits, 'value' => $value, 'average' => $qty === 0 ? 0 : Money::rupiah($value)->averageOver($qty)->amount,
            ];
            $sum['qty'] += $qty;
            $sum['value'] += $value;
        }

        $tables = [$this->table(
            'Frekuensi dan nilai per jenis tindakan',
            [
                new ReportColumn('code', 'Kode'), new ReportColumn('name', 'Tindakan'), new ReportColumn('category', 'Kategori'),
                new ReportColumn('qty', 'Jumlah', ColumnKind::Integer), new ReportColumn('visits', 'Kunjungan', ColumnKind::Integer),
                new ReportColumn('value', 'Nilai', ColumnKind::Money), new ReportColumn('average', 'Rata-rata tarif', ColumnKind::Money),
            ],
            $list,
            $list === [] ? null : $sum,
            'Belum ada tindakan pada rentang ini.',
        )];

        if (! $query->isOwn()) {
            $tables[] = $this->perDoctor($query);
        }

        return new ReportResult($this->key(), $f->range, [
            new ReportMetric('Jenis tindakan', count($list)),
            new ReportMetric('Jumlah tindakan', $sum['qty']),
            new ReportMetric('Nilai tindakan', $sum['value'], ColumnKind::Money),
        ], $tables, [
            'Tindakan yang ditarik kembali dari kunjungan tidak dihitung. Nilai memakai tarif saat tindakan dicatat, sebelum diskon pada tagihan.',
            ...($query->isOwn() ? ['Hanya tindakan dari kunjungan yang Anda tangani.'] : []),
        ]);
    }

    private function perDoctor(ReportQuery $query): ReportTable
    {
        $f = $query->filters;
        $rows = $this->clinical->procedures($f->range, $f->doctorId)
            ->groupBy('e.doctor_id')
            ->selectRaw('e.doctor_id as doctor_id, SUM(ep.quantity) as qty, SUM(ep.quantity * ep.unit_price_amount) as value')
            ->get();
        $names = $this->source->names('doctors', array_map(fn ($r) => (string) $r->doctor_id, $rows->all()));
        $list = [];
        foreach ($rows as $r) {
            $list[] = ['doctor' => $names[(string) $r->doctor_id] ?? '(dokter tidak dikenal)', 'qty' => (int) $r->qty, 'value' => (int) $r->value];
        }
        usort($list, fn (array $a, array $b) => [$b['qty'], $a['doctor']] <=> [$a['qty'], $b['doctor']]);

        return $this->table(
            'Tindakan per dokter',
            [new ReportColumn('doctor', 'Dokter'), new ReportColumn('qty', 'Jumlah', ColumnKind::Integer), new ReportColumn('value', 'Nilai', ColumnKind::Money)],
            $list,
            null,
            'Belum ada tindakan pada rentang ini.',
        );
    }
}
