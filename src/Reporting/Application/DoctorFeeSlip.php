<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Reporting\Domain\DateRange;
use Src\Reporting\Domain\InvalidReportRequest;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\PdfRenderer;
use Src\Shared\Application\PdfRequest;

/**
 * P2-09: slip jasa dokter satu periode untuk diserahkan ke dokter. Memakai perhitungan yang sama dengan LAP-03 (jasa kotor per
 * tindakan, potongan biaya lab, jasa bersih per bulan). Slip tidak memuat data pasien. Izin `report.financial` dicek di route
 * (M9); pencetakan tercatat di audit (M10).
 */
final readonly class DoctorFeeSlip
{
    public function __construct(
        private DoctorFeeStatementSource $fees,
        private DoctorDirectory $doctors,
        private LetterheadProvider $letterhead,
        private PdfRenderer $pdf,
        private AuditLogger $audit,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @return array{contents: string, file_name: string}
     *
     * @throws InvalidReportRequest
     */
    public function render(string $actorId, string $doctorId, string $from, string $to): array
    {
        $range = DateRange::between($from, $to);
        $doctor = $this->doctors->find($doctorId) ?? throw new InvalidReportRequest('report.not_found', 'Dokter tidak ditemukan.');
        $statement = $this->fees->statements($range, $doctorId)[$doctorId] ?? null;
        if ($statement === null) {
            throw new InvalidReportRequest('report.no_data', 'Tidak ada tindakan atau biaya lab untuk dokter ini pada rentang tanggal tersebut.');
        }

        $months = [];
        $totals = ['base' => 0, 'gross' => 0, 'lab' => 0, 'net' => 0, 'uncovered' => 0];
        foreach ($statement['months'] as $m) {
            $months[] = [
                'month' => $m->month,
                'lines' => array_map(fn ($l) => ['code' => $l->code, 'name' => $l->name, 'qty' => $l->quantity, 'base' => $l->base, 'rule' => $l->rule, 'fee' => $l->fee], $m->lines),
                'base' => $m->base, 'gross' => $m->gross, 'lab_cost' => $m->labCost, 'lab' => $m->labDeduction, 'net' => $m->net, 'uncovered' => $m->uncovered,
            ];
            $totals['base'] += $m->base;
            $totals['gross'] += $m->gross;
            $totals['lab'] += $m->labDeduction;
            $totals['net'] += $m->net;
            $totals['uncovered'] += $m->uncovered;
        }

        $head = $this->letterhead->forDocument();
        $now = $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()));
        $data = [
            'letterhead' => $head->toArray(),
            'doctor' => ['name' => $doctor->name, 'sip' => $doctor->sipNo],
            'from' => $this->display($range->from),
            'to' => $this->display($range->to),
            'printed_at' => $now->format('d/m/Y H:i'),
            'scheme' => $statement['scheme']->describe(),
            'months' => $months,
            'totals' => $totals,
            'place' => $head->city,
        ];
        $contents = $this->pdf->render(new PdfRequest('pdf.doctor-fee-slip', $data, 'Slip jasa dokter '.$doctor->name));
        $this->audit->record(new AuditEntry($actorId, 'report.fee_slip_printed', 'doctor', $doctorId, null, ['from' => $range->from, 'to' => $range->to, 'net' => $totals['net']]));

        return ['contents' => $contents, 'file_name' => 'slip-jasa-'.$range->from.'-'.$range->to];
    }

    private function display(string $date): string
    {
        return implode('/', array_reverse(explode('-', $date)));
    }
}
