<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Reporting\Domain\DateRange;

/**
 * Kueri kunjungan, janji, dan tindakan yang dipakai beberapa laporan dan dasbor.
 */
final readonly class ClinicalQueries
{
    /** Janji yang seharusnya hadir: sudah datang atau ditandai tidak datang. Dasar tingkat janji tidak datang. */
    private const ATTENDANCE_STATUSES = ['checked_in', 'in_progress', 'completed', 'no_show'];

    public function __construct(private ReportSource $source) {}

    /**
     * Kunjungan yang dimulai dalam rentang.
     */
    public function encounters(DateRange $range, ?string $doctorId = null): Builder
    {
        $query = $this->source->from('encounters', 'e');
        $this->source->within($query, 'e.started_at', $range);
        if ($doctorId !== null) {
            $query->where('e.doctor_id', $doctorId);
        }

        return $query;
    }

    public function visitCount(DateRange $range, ?string $doctorId = null): int
    {
        return $this->encounters($range, $doctorId)->count();
    }

    /**
     * Pasien yang kunjungan pertamanya (pada dokter yang dipilih, bila ada) jatuh dalam rentang.
     */
    public function firstVisits(DateRange $range, ?string $doctorId = null): Builder
    {
        [$start, $end] = $this->source->bounds($range);
        $inner = $this->source->from('encounters', 'e');
        if ($doctorId !== null) {
            $inner->where('e.doctor_id', $doctorId);
        }
        $inner->groupBy('e.patient_id')
            ->havingRaw('MIN(e.started_at) >= ? AND MIN(e.started_at) < ?', [$start, $end])
            ->selectRaw('e.patient_id as patient_id, MIN(e.started_at) as first_at');

        return DB::query()->fromSub($inner, 'fv');
    }

    public function newPatientCount(DateRange $range, ?string $doctorId = null): int
    {
        return $this->firstVisits($range, $doctorId)->count();
    }

    /**
     * Janji dalam rentang menurut jam mulai.
     */
    public function appointments(DateRange $range, ?string $doctorId = null): Builder
    {
        $query = $this->source->from('appointments', 'a');
        $this->source->within($query, 'a.starts_at', $range);
        if ($doctorId !== null) {
            $query->where('a.doctor_id', $doctorId);
        }

        return $query;
    }

    /**
     * Tidak datang dan janji yang seharusnya hadir.
     *
     * @return array{no_show: int, due: int}
     */
    public function attendance(DateRange $range, ?string $doctorId = null): array
    {
        $row = $this->appointments($range, $doctorId)
            ->whereIn('a.status', self::ATTENDANCE_STATUSES)
            ->selectRaw("COALESCE(SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END), 0) as no_show, COUNT(*) as due")
            ->first();

        return ['no_show' => (int) ($row->no_show ?? 0), 'due' => (int) ($row->due ?? 0)];
    }

    /**
     * Kunjungan ber-janji yang sudah dimulai pemeriksaannya, dengan waktu tunggu: dari check-in sampai catatan pertama
     * atau anamnesis, mana yang lebih dulu. Dasar untuk rata-rata waktu tunggu.
     */
    public function waits(DateRange $range, ?string $doctorId = null): Builder
    {
        $firstNote = $this->source->from('clinical_notes', 'cn', false)
            ->groupBy('cn.encounter_id')
            ->selectRaw('cn.encounter_id as encounter_id, MIN(cn.authored_at) as first_at');

        $query = $this->encounters($range, $doctorId)
            ->join('appointments as a', 'a.id', '=', 'e.appointment_id')
            ->leftJoinSub($firstNote, 'n', 'n.encounter_id', '=', 'e.id')
            ->whereNotNull('a.checked_in_at')
            ->whereRaw('COALESCE(LEAST(e.anamnesis_at, n.first_at), e.anamnesis_at, n.first_at) IS NOT NULL')
            ->whereRaw('COALESCE(LEAST(e.anamnesis_at, n.first_at), e.anamnesis_at, n.first_at) >= a.checked_in_at');

        return $query;
    }

    /** Jumlah kunjungan dan total menit tunggu; dipakai dengan `waits()`. */
    public const WAIT_TOTALS = 'COUNT(*) as n, COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.checked_in_at, COALESCE(LEAST(e.anamnesis_at, n.first_at), e.anamnesis_at, n.first_at))), 0) as total';

    /** Sama dengan WAIT_TOTALS dengan kolom dokter, untuk pengelompokan per dokter. */
    public const WAIT_TOTALS_BY_DOCTOR = 'e.doctor_id as doctor_id, COUNT(*) as n, COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.checked_in_at, COALESCE(LEAST(e.anamnesis_at, n.first_at), e.anamnesis_at, n.first_at))), 0) as total';

    /**
     * Tindakan yang dikerjakan dalam rentang (tidak termasuk yang ditarik kembali).
     */
    public function procedures(DateRange $range, ?string $doctorId = null): Builder
    {
        $query = $this->source->from('encounter_procedures', 'ep')
            ->join('encounters as e', 'e.id', '=', 'ep.encounter_id');
        $this->source->within($query, 'ep.performed_at', $range);
        if ($doctorId !== null) {
            $query->where('e.doctor_id', $doctorId);
        }

        return $query;
    }
}
