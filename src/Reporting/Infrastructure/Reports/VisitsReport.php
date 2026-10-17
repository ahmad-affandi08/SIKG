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
use Src\Reporting\Infrastructure\Queries\ClinicalQueries;
use Src\Reporting\Infrastructure\Queries\LocalTime;
use Src\Reporting\Infrastructure\Queries\ReportSource;

/**
 * LAP-04: kunjungan per hari atau bulan, pasien baru dan lama, janji tidak datang, dan waktu tunggu.
 */
final readonly class VisitsReport extends AbstractReport
{
    public function __construct(private ClinicalQueries $clinical, private ReportSource $source) {}

    public function key(): ReportKey
    {
        return ReportKey::Visits;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $f = $query->filters;
        $visits = $this->clinical->visitCount($f->range, $f->doctorId);
        $new = $this->clinical->newPatientCount($f->range, $f->doctorId);
        $attendance = $this->clinical->attendance($f->range, $f->doctorId);
        $wait = $this->waitSummary($query);

        return new ReportResult($this->key(), $f->range, [
            new ReportMetric('Jumlah kunjungan', $visits),
            new ReportMetric('Pasien baru', $new),
            new ReportMetric('Pasien lama', max(0, $visits - $new)),
            new ReportMetric('Janji tidak datang', $attendance['no_show']),
            new ReportMetric('Tingkat tidak datang', $this->basisPoints($attendance['no_show'], $attendance['due']), ColumnKind::Percent),
            new ReportMetric('Rata-rata waktu tunggu', $wait['count'] === 0 ? null : intdiv($wait['total'] + intdiv($wait['count'], 2), $wait['count']), ColumnKind::Minutes),
        ], [$this->periodTable($query), $this->doctorTable($query)], [
            'Kunjungan = pemeriksaan yang dimulai dalam rentang. Pasien baru = pasien yang kunjungan pertamanya (pada dokter yang dipilih, bila ada) jatuh dalam rentang; sisanya pasien lama.',
            'Tingkat tidak datang = janji Tidak datang dibagi janji yang seharusnya hadir (sudah check-in, sedang diperiksa, selesai, atau tidak datang). Janji batal tidak dihitung.',
            'Waktu tunggu = menit dari check-in sampai anamnesis atau catatan pertama, mana yang lebih dulu; hanya kunjungan ber-janji yang sudah dimulai.',
        ]);
    }

    /**
     * @return array{count: int, total: int}
     */
    private function waitSummary(ReportQuery $query, ?string $doctorId = null): array
    {
        $f = $query->filters;
        $row = $this->clinical->waits($f->range, $doctorId ?? $f->doctorId)->selectRaw(ClinicalQueries::WAIT_TOTALS)->first();

        return ['count' => (int) ($row->n ?? 0), 'total' => (int) ($row->total ?? 0)];
    }

    private function periodTable(ReportQuery $query): ReportTable
    {
        $f = $query->filters;
        $monthly = $f->grain === ReportFilters::GRAIN_MONTH;
        $offset = $this->source->offset($f->range);
        $encounterExpr = $monthly ? LocalTime::ENCOUNTER_STARTED_MONTH : LocalTime::ENCOUNTER_STARTED_DAY;
        $firstExpr = $monthly ? LocalTime::FIRST_VISIT_MONTH : LocalTime::FIRST_VISIT_DAY;
        $appointmentExpr = $monthly ? LocalTime::APPOINTMENT_START_MONTH : LocalTime::APPOINTMENT_START_DAY;

        $visitRows = $this->clinical->encounters($f->range, $f->doctorId)->groupBy('period')->selectRaw($encounterExpr.' as period, COUNT(*) as n', [$offset])->get();
        $newRows = $this->clinical->firstVisits($f->range, $f->doctorId)->groupBy('period')->selectRaw($firstExpr.' as period, COUNT(*) as n', [$offset])->get();
        $noShowRows = $this->clinical->appointments($f->range, $f->doctorId)->where('a.status', 'no_show')->groupBy('period')->selectRaw($appointmentExpr.' as period, COUNT(*) as n', [$offset])->get();

        $by = [];
        foreach ([['visits', $visitRows], ['new', $newRows], ['no_show', $noShowRows]] as [$field, $rows]) {
            foreach ($rows as $r) {
                $by[(string) $r->period] ??= ['period' => (string) $r->period, 'visits' => 0, 'new' => 0, 'returning' => 0, 'no_show' => 0];
                $by[(string) $r->period][$field] = (int) $r->n;
            }
        }
        ksort($by);
        $list = [];
        $sum = ['period' => 'Total', 'visits' => 0, 'new' => 0, 'returning' => 0, 'no_show' => 0];
        foreach ($by as $row) {
            $row['returning'] = max(0, $row['visits'] - $row['new']);
            $list[] = $row;
            foreach (['visits', 'new', 'returning', 'no_show'] as $k) {
                $sum[$k] += $row[$k];
            }
        }

        return $this->table(
            $monthly ? 'Kunjungan per bulan' : 'Kunjungan per hari',
            [
                new ReportColumn('period', $monthly ? 'Bulan' : 'Tanggal', $monthly ? ColumnKind::Text : ColumnKind::Date),
                new ReportColumn('visits', 'Kunjungan', ColumnKind::Integer),
                new ReportColumn('new', 'Pasien baru', ColumnKind::Integer),
                new ReportColumn('returning', 'Pasien lama', ColumnKind::Integer),
                new ReportColumn('no_show', 'Tidak datang', ColumnKind::Integer),
            ],
            $list,
            $list === [] ? null : $sum,
            'Belum ada kunjungan pada rentang ini.',
        );
    }

    private function doctorTable(ReportQuery $query): ReportTable
    {
        $f = $query->filters;
        $visitRows = $this->clinical->encounters($f->range, $f->doctorId)->groupBy('e.doctor_id')->selectRaw('e.doctor_id as doctor_id, COUNT(*) as n')->get();
        $noShowRows = $this->clinical->appointments($f->range, $f->doctorId)->where('a.status', 'no_show')->groupBy('a.doctor_id')->selectRaw('a.doctor_id as doctor_id, COUNT(*) as n')->get();
        $waitRows = $this->clinical->waits($f->range, $f->doctorId)->groupBy('e.doctor_id')->selectRaw(ClinicalQueries::WAIT_TOTALS_BY_DOCTOR)->get();

        $by = [];
        foreach ($visitRows as $r) {
            $by[(string) $r->doctor_id] = ['doctor_id' => (string) $r->doctor_id, 'visits' => (int) $r->n, 'no_show' => 0, 'wait' => null];
        }
        foreach ($noShowRows as $r) {
            $by[(string) $r->doctor_id] ??= ['doctor_id' => (string) $r->doctor_id, 'visits' => 0, 'no_show' => 0, 'wait' => null];
            $by[(string) $r->doctor_id]['no_show'] = (int) $r->n;
        }
        foreach ($waitRows as $r) {
            if (isset($by[(string) $r->doctor_id]) && (int) $r->n > 0) {
                $by[(string) $r->doctor_id]['wait'] = intdiv((int) $r->total + intdiv((int) $r->n, 2), (int) $r->n);
            }
        }
        $names = $this->source->names('doctors', array_keys($by));
        $list = [];
        foreach ($by as $id => $row) {
            $list[] = ['doctor' => $names[$id] ?? '(dokter tidak dikenal)', 'visits' => $row['visits'], 'no_show' => $row['no_show'], 'wait' => $row['wait']];
        }
        usort($list, fn (array $a, array $b) => [$b['visits'], $a['doctor']] <=> [$a['visits'], $b['doctor']]);

        return $this->table(
            'Per dokter',
            [new ReportColumn('doctor', 'Dokter'), new ReportColumn('visits', 'Kunjungan', ColumnKind::Integer), new ReportColumn('no_show', 'Tidak datang', ColumnKind::Integer), new ReportColumn('wait', 'Rata-rata tunggu', ColumnKind::Minutes)],
            $list,
            null,
            'Belum ada kunjungan pada rentang ini.',
        );
    }
}
