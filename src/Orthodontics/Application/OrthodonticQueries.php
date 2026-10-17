<?php

declare(strict_types=1);

namespace Src\Orthodontics\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Contracts\InvoiceClaimGateway;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinical\Contracts\EncounterFileLookup;
use Src\Clinical\Contracts\EncounterLookup;
use Src\Orthodontics\Domain\Appliance;
use Src\Orthodontics\Domain\CaseStatus;
use Src\Orthodontics\Domain\InstallmentRecord;
use Src\Orthodontics\Domain\InstallmentSchedule;
use Src\Orthodontics\Domain\InvalidOrthodontics;
use Src\Orthodontics\Domain\OrthoCase;
use Src\Orthodontics\Domain\OrthoRepository;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\Clock;

/**
 * P2-04: daftar paket (dengan kontrol berikutnya dan sisa cicilan), rincian paket, dan data formulir. Catatan klinis (rencana,
 * kontrol, foto) hanya dikembalikan bila pemanggil berhak membuka rekam medis; petugas kasir melihat paket dan cicilannya saja.
 */
final readonly class OrthodonticQueries
{
    public function __construct(
        private OrthoRepository $cases,
        private PatientLookup $patients,
        private DoctorDirectory $doctors,
        private EncounterLookup $encounters,
        private EncounterFileLookup $files,
        private InvoiceClaimGateway $invoices,
        private AccessLogger $access,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @return array{cases: list<array<string, mixed>>, statuses: list<array{value: string, label: string}>}
     */
    public function list(?string $status): array
    {
        $cases = $this->cases->list($status, null, 200);

        return ['cases' => $this->rows($cases), 'statuses' => array_map(fn (CaseStatus $s) => ['value' => $s->value, 'label' => $s->label()], CaseStatus::cases())];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string $caseId, bool $withClinical, string $route): array
    {
        $case = $this->cases->find($caseId) ?? throw new InvalidOrthodontics('ortho.not_found', 'Paket perawatan tidak ditemukan.');
        $this->access->patientRecordOpened($case->patientId, $route);
        [$row] = $this->rows([$case]);
        $installments = $this->cases->installments($caseId);
        $invoices = $this->invoices->byIds(array_values(array_filter(array_map(fn ($i) => $i->invoiceId, $installments))));

        $controls = [];
        if ($withClinical) {
            $records = $this->cases->controls($caseId);
            $photos = $this->files->imagesFor(array_values(array_unique(array_filter(array_map(fn ($c) => $c->encounterId, $records)))));
            $controls = array_map(fn ($c) => [
                'id' => $c->id,
                'control_date' => $c->controlDate,
                'encounter_id' => $c->encounterId,
                'wire_or_tray' => $c->wireOrTray,
                'adjustments' => $c->adjustments,
                'notes' => $c->notes,
                'next_control_date' => $c->nextControlDate,
                'photos' => array_map(fn ($f) => ['id' => $f->id, 'kind_label' => $f->kindLabel, 'caption' => $f->caption], $c->encounterId === null ? [] : ($photos[$c->encounterId] ?? [])),
            ], $records);
        }

        return [
            'case' => $row + ['plan_notes' => $withClinical ? $case->planNotes : null, 'ended_note' => $case->endedNote(), 'ended_at' => $case->endedAt()?->format('c'), 'total_fee_amount' => $case->totalFeeAmount, 'expected_months' => $case->expectedMonths],
            'installments' => array_map(fn (InstallmentRecord $i) => [
                'id' => $i->id,
                'seq' => $i->seq,
                'due_date' => $i->dueDate,
                'amount' => $i->amount,
                'status' => $i->status,
                'status_label' => match ($i->status) {
                    InstallmentRecord::SCHEDULED => 'Dijadwalkan',
                    InstallmentRecord::INVOICED => 'Ditagihkan',
                    default => 'Dibatalkan',
                },
                'invoice_id' => $i->invoiceId,
                'invoice_number' => $invoices[$i->invoiceId ?? '']->number ?? null,
                'invoice_status_label' => $invoices[$i->invoiceId ?? '']->statusLabel ?? null,
                'overdue' => $i->status === InstallmentRecord::SCHEDULED && $i->dueDate < $this->today(),
            ], $installments),
            'controls' => $controls,
            'open_encounters' => array_map(fn ($e) => ['value' => $e->id, 'label' => 'Kunjungan berjalan '.substr($e->id, -6)], $this->encounters->openForPatient($case->patientId)),
            'can_clinical' => $withClinical,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createForm(string $patientId): array
    {
        $patient = $this->patients->summaries([$patientId])[$patientId] ?? throw new InvalidOrthodontics('ortho.not_found', 'Pasien tidak ditemukan.');
        $this->access->patientRecordOpened($patientId, 'orthodontics.create');

        return [
            'patient' => ['id' => $patient->id, 'name' => $patient->name, 'medical_record_no' => $patient->medicalRecordNo],
            'doctors' => array_map(fn ($d) => ['value' => $d->id, 'label' => $d->name], $this->doctors->active()),
            'appliances' => array_map(fn (Appliance $a) => ['value' => $a->value, 'label' => $a->label()], Appliance::cases()),
            'today' => $this->today(),
            'max_installments' => InstallmentSchedule::MAX_COUNT,
        ];
    }

    /**
     * @param  list<OrthoCase>  $cases
     * @return list<array<string, mixed>>
     */
    private function rows(array $cases): array
    {
        $ids = array_map(fn (OrthoCase $c) => $c->id, $cases);
        $patients = $this->patients->summaries(array_values(array_unique(array_map(fn (OrthoCase $c) => $c->patientId, $cases))));
        $doctors = $this->doctors->byIds(array_values(array_unique(array_map(fn (OrthoCase $c) => $c->doctorId, $cases))));
        $latest = $this->cases->latestControls($ids);
        $unbilled = $this->cases->unbilledAmounts($ids);
        $today = $this->today();

        return array_map(function (OrthoCase $c) use ($patients, $doctors, $latest, $unbilled, $today): array {
            $control = $latest[$c->id] ?? null;
            $next = $control === null ? InstallmentSchedule::monthsAfter(new DateTimeImmutable($c->startDate), 1)->format('Y-m-d') : $control->nextControlDate;

            return [
                'id' => $c->id,
                'patient_id' => $c->patientId,
                'patient_name' => $patients[$c->patientId]->name ?? '-',
                'medical_record_no' => $patients[$c->patientId]->medicalRecordNo ?? '-',
                'doctor_name' => $doctors[$c->doctorId]->name ?? '-',
                'title' => $c->title,
                'appliance_label' => $c->appliance->label(),
                'status' => $c->status()->value,
                'status_label' => $c->status()->label(),
                'start_date' => $c->startDate,
                'last_control_date' => $control?->controlDate,
                'next_control_date' => $c->isActive() ? $next : null,
                'control_overdue' => $c->isActive() && $next !== null && $next < $today,
                'unbilled_amount' => $unbilled[$c->id] ?? 0,
                'total_fee_amount' => $c->totalFeeAmount,
            ];
        }, $cases);
    }

    private function today(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
    }
}
