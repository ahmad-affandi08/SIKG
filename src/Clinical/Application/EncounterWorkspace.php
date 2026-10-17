<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use Src\Clinic\Contracts\ChairDirectory;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinical\Domain\ClinicalFileRepository;
use Src\Clinical\Domain\EncounterNotFound;
use Src\Clinical\Domain\EncounterRepository;
use Src\IdentityAccess\Contracts\UserLookup;
use Src\Patient\Contracts\PatientAlerts;
use Src\Patient\Contracts\PatientLookup;
use Src\Pharmacy\Contracts\PrescriptionLookup;
use Src\Shared\Application\AccessLogger;

/**
 * Data layar pemeriksaan satu kunjungan: identitas, anamnesis, SOAP terkini beserta riwayat versi, diagnosa, tindakan,
 * addendum, dan banner peringatan medis. Membuka kunjungan tercatat di access log (M10).
 */
final readonly class EncounterWorkspace
{
    public function __construct(
        private EncounterRepository $encounters,
        private ClinicalFileRepository $files,
        private PatientLookup $patients,
        private PatientAlerts $alerts,
        private DoctorDirectory $doctors,
        private ChairDirectory $chairs,
        private UserLookup $users,
        private AccessLogger $access,
        private PrescriptionLookup $prescriptions,
        private OdontogramService $odontogram,
        private TreatmentPlans $plans,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(string $encounterId, string $route): array
    {
        $encounter = $this->encounters->find($encounterId) ?? throw new EncounterNotFound;
        $this->access->patientRecordOpened($encounter->patientId, $route);

        $patient = $this->patients->summaries([$encounter->patientId])[$encounter->patientId] ?? null;
        $doctor = $this->doctors->byIds([$encounter->doctorId])[$encounter->doctorId] ?? null;
        $chair = $encounter->chairId === null ? null : ($this->chairs->byIds([$encounter->chairId])[$encounter->chairId] ?? null);

        $history = $this->encounters->noteHistory($encounterId);
        $authorNames = $this->users->names(array_values(array_unique(array_map(fn ($n) => $n->authorId, $history))));
        $present = fn ($n) => [
            'id' => $n->id,
            'type' => $n->type->value,
            'section' => $n->section?->value,
            'body' => $n->body,
            'author' => $authorNames[$n->authorId] ?? '-',
            'authored_at' => $n->authoredAt->format('c'),
            'reason' => $n->reason,
            'supersedes_id' => $n->supersedesId,
        ];

        $files = $this->files->forEncounter($encounterId);
        $uploaders = $this->users->names(array_values(array_unique(array_map(fn ($f) => $f->uploadedBy, $files))));
        $superseded = array_filter(array_map(fn ($n) => $n->supersedesId, $history));
        $current = array_values(array_filter($history, fn ($n) => ! in_array($n->id, $superseded, true)));

        return [
            'encounter' => [
                'id' => $encounter->id,
                'status' => $encounter->status->value,
                'status_label' => $encounter->status->label(),
                'is_closed' => $encounter->isClosed(),
                'started_at' => $encounter->startedAt->format('c'),
                'closed_at' => $encounter->closedAt?->format('c'),
                'appointment_id' => $encounter->appointmentId,
                'patient_id' => $encounter->patientId,
                'patient_name' => $patient?->name,
                'medical_record_no' => $patient?->medicalRecordNo,
                'doctor_id' => $encounter->doctorId,
                'doctor_name' => $doctor?->name,
                'chair_id' => $encounter->chairId,
                'chair_name' => $chair?->name,
                'chief_complaint' => $encounter->chiefComplaint,
                'complaint_history' => $encounter->complaintHistory,
                'vitals' => $encounter->vitals->toArray(),
                'anamnesis_at' => $encounter->anamnesisAt?->format('c'),
            ],
            'notes' => array_map($present, array_values(array_filter($current, fn ($n) => $n->type->value !== 'addendum'))),
            'addenda' => array_map($present, array_values(array_filter($current, fn ($n) => $n->type->value === 'addendum'))),
            'note_history' => array_map($present, $history),
            'diagnoses' => array_map(fn ($d) => $d->toArray(), $this->encounters->diagnoses($encounterId)),
            'procedures' => array_map(fn ($p) => $p->toArray(), $this->encounters->procedures($encounterId)),
            'files' => array_map(fn ($f) => [
                'id' => $f->id,
                'kind' => $f->kind->value,
                'kind_label' => $f->kind->label(),
                'tooth_fdi' => $f->toothFdi,
                'mime' => $f->mime,
                'size' => $f->size,
                'caption' => $f->caption,
                'uploaded_at' => $f->uploadedAt->format('c'),
                'uploaded_by' => $uploaders[$f->uploadedBy] ?? '-',
            ], $files),
            // ODO-05: usulan pembaruan odontogram dan RM-06: langkah rencana perawatan yang dapat dikerjakan pada kunjungan ini.
            'odontogram_suggestions' => $encounter->isClosed() ? [] : $this->odontogram->suggestions($encounterId),
            'plan_items' => $encounter->isClosed() ? [] : array_map(fn ($i) => [
                'id' => $i->id,
                'plan_id' => $i->planId,
                'code' => $i->procedureCode,
                'name' => $i->procedureName,
                'tooth_fdi' => $i->toothFdi,
                'quantity' => $i->quantity,
                'estimate_amount' => $i->estimateAmount,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
            ], $this->plans->openItems($encounter->patientId)),
            'prescriptions' => array_map(fn ($p) => $p->toArray(), $this->prescriptions->forEncounters([$encounterId])[$encounterId] ?? []),
            'warnings' => array_map(fn ($a) => $a->toArray(), $this->alerts->warnings($encounter->patientId)),
            'medical_history' => array_map(fn ($a) => $a->toArray(), $this->alerts->history($encounter->patientId)),
            'chairs' => array_map(fn ($c) => ['value' => $c->id, 'label' => $c->name], $this->chairs->active()),
        ];
    }
}
