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

/**
 * LAP-09: kelengkapan rekam medis. Kunjungan yang sudah ditutup harus memiliki diagnosa dan tindakan (indikator PRD 00);
 * catatan SOAP dilaporkan terpisah. Kunjungan yang belum ditutup dokter sampai hari berikutnya dihitung sebagai belum
 * ditandatangani (penutupan oleh dokter adalah tanda tangan RM-07). Dokter hanya melihat kunjungannya sendiri.
 */
final readonly class RecordCompletenessReport extends AbstractReport
{
    private const HAS_DIAGNOSIS = 'EXISTS (SELECT 1 FROM encounter_diagnoses ed WHERE ed.encounter_id = e.id AND ed.deleted_at IS NULL)';

    private const HAS_PROCEDURE = 'EXISTS (SELECT 1 FROM encounter_procedures ep WHERE ep.encounter_id = e.id AND ep.deleted_at IS NULL)';

    private const HAS_NOTE = 'EXISTS (SELECT 1 FROM clinical_notes cn WHERE cn.encounter_id = e.id)';

    public function __construct(private ClinicalQueries $clinical, private ReportSource $source, private ReportLookups $lookups) {}

    public function key(): ReportKey
    {
        return ReportKey::RecordCompleteness;
    }

    public function run(ReportQuery $query): ReportResult
    {
        $f = $query->filters;
        $doctorId = $this->doctorScope($query, $this->lookups);
        $startOfToday = $this->source->startOfDay($this->source->today());

        $sql = sprintf(
            "e.doctor_id as doctor_id,
            COALESCE(SUM(CASE WHEN e.status = 'closed' THEN 1 ELSE 0 END), 0) as closed,
            COALESCE(SUM(CASE WHEN e.status = 'closed' AND %1\$s AND %2\$s THEN 1 ELSE 0 END), 0) as complete,
            COALESCE(SUM(CASE WHEN e.status = 'closed' AND NOT %1\$s THEN 1 ELSE 0 END), 0) as no_diagnosis,
            COALESCE(SUM(CASE WHEN e.status = 'closed' AND NOT %2\$s THEN 1 ELSE 0 END), 0) as no_procedure,
            COALESCE(SUM(CASE WHEN e.status = 'closed' AND NOT %3\$s THEN 1 ELSE 0 END), 0) as no_note,
            COALESCE(SUM(CASE WHEN e.status = 'open' AND e.started_at < ? THEN 1 ELSE 0 END), 0) as unsigned_count",
            self::HAS_DIAGNOSIS,
            self::HAS_PROCEDURE,
            self::HAS_NOTE,
        );
        $rows = $this->clinical->encounters($f->range, $doctorId)->groupBy('e.doctor_id')->selectRaw($sql, [$startOfToday])->get();
        $names = $this->source->names('doctors', array_map(fn ($r) => (string) $r->doctor_id, $rows->all()));

        $perDoctor = [];
        $sum = ['doctor' => 'Total', 'closed' => 0, 'complete' => 0, 'no_diagnosis' => 0, 'no_procedure' => 0, 'no_note' => 0, 'unsigned' => 0, 'rate' => 0];
        foreach ($rows as $r) {
            $row = [
                'doctor' => $names[(string) $r->doctor_id] ?? '(dokter tidak dikenal)', 'closed' => (int) $r->closed, 'complete' => (int) $r->complete, 'no_diagnosis' => (int) $r->no_diagnosis,
                'no_procedure' => (int) $r->no_procedure, 'no_note' => (int) $r->no_note, 'unsigned' => (int) $r->unsigned_count, 'rate' => $this->basisPoints((int) $r->complete, (int) $r->closed),
            ];
            $perDoctor[] = $row;
            foreach (['closed', 'complete', 'no_diagnosis', 'no_procedure', 'no_note', 'unsigned'] as $k) {
                $sum[$k] += $row[$k];
            }
        }
        $sum['rate'] = $this->basisPoints($sum['complete'], $sum['closed']);
        usort($perDoctor, fn (array $a, array $b) => $a['doctor'] <=> $b['doctor']);

        return new ReportResult($this->key(), $f->range, [
            new ReportMetric('Kunjungan ditutup', $sum['closed']),
            new ReportMetric('Kelengkapan (diagnosa dan tindakan)', $sum['rate'], ColumnKind::Percent),
            new ReportMetric('Tanpa diagnosa', $sum['no_diagnosis']),
            new ReportMetric('Tanpa tindakan', $sum['no_procedure']),
            new ReportMetric('Tanpa catatan', $sum['no_note']),
            new ReportMetric('Belum ditutup dokter', $sum['unsigned']),
        ], [
            $this->table(
                'Kelengkapan per dokter',
                [
                    new ReportColumn('doctor', 'Dokter'), new ReportColumn('closed', 'Ditutup', ColumnKind::Integer), new ReportColumn('complete', 'Lengkap', ColumnKind::Integer),
                    new ReportColumn('no_diagnosis', 'Tanpa diagnosa', ColumnKind::Integer), new ReportColumn('no_procedure', 'Tanpa tindakan', ColumnKind::Integer),
                    new ReportColumn('no_note', 'Tanpa catatan', ColumnKind::Integer), new ReportColumn('unsigned', 'Belum ditutup', ColumnKind::Integer), new ReportColumn('rate', 'Kelengkapan', ColumnKind::Percent),
                ],
                $perDoctor,
                $perDoctor === [] ? null : $sum,
                'Belum ada kunjungan pada rentang ini.',
            ),
            $this->incomplete($query, $doctorId, $startOfToday),
        ], [
            'Lengkap = kunjungan yang sudah ditutup dan memiliki sedikitnya satu diagnosa dan satu tindakan. Kelengkapan = lengkap dibagi kunjungan ditutup.',
            'Belum ditutup dokter = kunjungan yang masih terbuka sampai lewat hari kunjungan; penutupan oleh dokter adalah tanda tangan rekam medis.',
            ...($query->isOwn() ? ['Hanya kunjungan yang Anda tangani.'] : []),
        ]);
    }

    private function incomplete(ReportQuery $query, ?string $doctorId, string $startOfToday): ReportTable
    {
        $f = $query->filters;
        $closedMissing = sprintf(
            "e.status = 'closed' AND (NOT %s OR NOT %s OR NOT %s)",
            self::HAS_DIAGNOSIS,
            self::HAS_PROCEDURE,
            self::HAS_NOTE,
        );
        $flags = sprintf(
            'e.id as id, e.started_at as started_at, e.status as status, e.doctor_id as doctor_id, pt.name as patient, pt.medical_record_no as mr,
            CASE WHEN %s THEN 0 ELSE 1 END as no_diagnosis, CASE WHEN %s THEN 0 ELSE 1 END as no_procedure, CASE WHEN %s THEN 0 ELSE 1 END as no_note',
            self::HAS_DIAGNOSIS,
            self::HAS_PROCEDURE,
            self::HAS_NOTE,
        );
        $rows = $this->clinical->encounters($f->range, $doctorId)
            ->join('patients as pt', 'pt.id', '=', 'e.patient_id')
            ->where(function ($q) use ($closedMissing, $startOfToday): void {
                $q->whereRaw($closedMissing)->orWhereRaw("e.status = 'open' AND e.started_at < ?", [$startOfToday]);
            })
            ->orderBy('e.started_at')->limit(ReportTable::MAX_ROWS + 1)
            ->selectRaw($flags)->get();
        $names = $this->source->names('doctors', array_map(fn ($r) => (string) $r->doctor_id, $rows->all()));

        $list = [];
        foreach ($rows as $r) {
            $missing = [];
            if ($r->status === 'open') {
                $missing[] = 'Belum ditutup dokter';
            } else {
                foreach (['no_diagnosis' => 'Diagnosa', 'no_procedure' => 'Tindakan', 'no_note' => 'Catatan'] as $flag => $label) {
                    if ((int) $r->{$flag} === 1) {
                        $missing[] = $label;
                    }
                }
            }
            $list[] = [
                'started_at' => $this->source->localTime((string) $r->started_at), 'mr' => (string) $r->mr, 'patient' => (string) $r->patient,
                'doctor' => $names[(string) $r->doctor_id] ?? '(dokter tidak dikenal)', 'missing' => implode(', ', $missing),
            ];
        }

        return $this->table(
            'Kunjungan yang perlu dilengkapi',
            [new ReportColumn('started_at', 'Kunjungan', ColumnKind::DateTime), new ReportColumn('mr', 'No. RM'), new ReportColumn('patient', 'Pasien'), new ReportColumn('doctor', 'Dokter'), new ReportColumn('missing', 'Yang kurang')],
            $list,
            null,
            'Semua kunjungan pada rentang ini lengkap.',
        );
    }
}
