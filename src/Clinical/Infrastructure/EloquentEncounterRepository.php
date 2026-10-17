<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use DateTimeImmutable;
use Src\Clinical\Domain\EncounterDiagnosisRecord;
use Src\Clinical\Domain\EncounterProcedureRecord;
use Src\Clinical\Domain\EncounterRecord;
use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\EncounterStatus;
use Src\Clinical\Domain\NoteRecord;
use Src\Clinical\Domain\NoteSection;
use Src\Clinical\Domain\NoteType;
use Src\Clinical\Domain\ProcedureTarget;
use Src\Clinical\Domain\Vitals;
use Src\Clinical\Infrastructure\Eloquent\ClinicalNoteModel;
use Src\Clinical\Infrastructure\Eloquent\EncounterDiagnosisModel;
use Src\Clinical\Infrastructure\Eloquent\EncounterModel;
use Src\Clinical\Infrastructure\Eloquent\EncounterProcedureModel;

final class EloquentEncounterRepository implements EncounterRepository
{
    public function open(?string $actorId, string $patientId, ?string $appointmentId, string $doctorId, ?string $chairId, DateTimeImmutable $startedAt): string
    {
        $row = new EncounterModel;
        $row->forceFill([
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'doctor_id' => $doctorId,
            'chair_id' => $chairId,
            'started_at' => $startedAt,
            'status' => EncounterStatus::Open->value,
            'version' => 1,
            'created_by' => $actorId,
        ])->save();

        return $row->id;
    }

    public function find(string $id): ?EncounterRecord
    {
        $row = EncounterModel::query()->find($id);

        return $row === null ? null : $this->record($row);
    }

    public function findForUpdate(string $id): ?EncounterRecord
    {
        $row = EncounterModel::query()->lockForUpdate()->find($id);

        return $row === null ? null : $this->record($row);
    }

    public function findByAppointment(string $appointmentId): ?EncounterRecord
    {
        $row = EncounterModel::query()->where('appointment_id', $appointmentId)->first();

        return $row === null ? null : $this->record($row);
    }

    public function forPatient(string $patientId): array
    {
        return array_values(EncounterModel::query()->where('patient_id', $patientId)->orderByDesc('started_at')->orderByDesc('id')->get()
            ->map(fn (EncounterModel $e) => $this->record($e))->all());
    }

    public function saveAnamnesis(string $actorId, string $id, ?string $chiefComplaint, ?string $complaintHistory, Vitals $vitals, DateTimeImmutable $at): void
    {
        $row = EncounterModel::query()->findOrFail($id);
        $row->forceFill([
            'chief_complaint' => $chiefComplaint,
            'complaint_history' => $complaintHistory,
            'vitals' => $vitals->toArray(),
            'anamnesis_at' => $row->anamnesis_at ?? $at,
            'version' => $row->version + 1,
            'updated_by' => $actorId,
        ])->save();
    }

    public function close(string $actorId, string $id, DateTimeImmutable $at): void
    {
        $row = EncounterModel::query()->findOrFail($id);
        $row->forceFill(['status' => EncounterStatus::Closed->value, 'closed_at' => $at, 'closed_by' => $actorId, 'version' => $row->version + 1, 'updated_by' => $actorId])->save();
    }

    public function addNote(string $authorId, string $encounterId, NoteType $type, ?NoteSection $section, string $body, DateTimeImmutable $at, ?string $supersedesId = null, ?string $reason = null): string
    {
        $row = new ClinicalNoteModel;
        $row->forceFill([
            'encounter_id' => $encounterId,
            'type' => $type->value,
            'section' => $section?->value,
            'body' => $body,
            'author_id' => $authorId,
            'authored_at' => $at,
            'supersedes_id' => $supersedesId,
            'reason' => $reason,
        ])->save();

        return $row->id;
    }

    public function currentNotes(string $encounterId): array
    {
        $superseded = ClinicalNoteModel::query()->where('encounter_id', $encounterId)->whereNotNull('supersedes_id')->pluck('supersedes_id')->all();

        return array_values(ClinicalNoteModel::query()->where('encounter_id', $encounterId)->whereNotIn('id', $superseded)
            ->orderBy('authored_at')->orderBy('id')->get()->map(fn (ClinicalNoteModel $n) => $this->note($n))->all());
    }

    public function noteHistory(string $encounterId): array
    {
        return array_values(ClinicalNoteModel::query()->where('encounter_id', $encounterId)->orderBy('authored_at')->orderBy('id')->get()
            ->map(fn (ClinicalNoteModel $n) => $this->note($n))->all());
    }

    public function findNote(string $encounterId, string $noteId): ?NoteRecord
    {
        $row = ClinicalNoteModel::query()->where('encounter_id', $encounterId)->find($noteId);

        return $row === null ? null : $this->note($row);
    }

    public function addDiagnosis(string $actorId, string $encounterId, string $diagnosisId, string $code, string $name, ?int $toothFdi, ?string $notes): string
    {
        $row = new EncounterDiagnosisModel;
        $row->forceFill(['encounter_id' => $encounterId, 'diagnosis_id' => $diagnosisId, 'icd10_code' => $code, 'diagnosis_name' => $name, 'tooth_fdi' => $toothFdi, 'notes' => $notes, 'created_by' => $actorId])->save();

        return $row->id;
    }

    public function diagnoses(string $encounterId): array
    {
        return array_values(EncounterDiagnosisModel::query()->where('encounter_id', $encounterId)->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (EncounterDiagnosisModel $d) => $this->diagnosis($d))->all());
    }

    public function findDiagnosis(string $encounterId, string $id): ?EncounterDiagnosisRecord
    {
        $row = EncounterDiagnosisModel::query()->where('encounter_id', $encounterId)->find($id);

        return $row === null ? null : $this->diagnosis($row);
    }

    public function removeDiagnosis(string $actorId, string $encounterId, string $id): void
    {
        $row = EncounterDiagnosisModel::query()->where('encounter_id', $encounterId)->findOrFail($id);
        $row->forceFill(['updated_by' => $actorId])->save();
        $row->delete();
    }

    public function addProcedure(
        string $actorId,
        string $encounterId,
        string $procedureId,
        string $code,
        string $name,
        string $scope,
        ProcedureTarget $target,
        int $quantity,
        int $unitPriceAmount,
        ?string $notes,
        DateTimeImmutable $at,
    ): string {
        $row = new EncounterProcedureModel;
        $row->forceFill([
            'encounter_id' => $encounterId,
            'procedure_id' => $procedureId,
            'procedure_code' => $code,
            'procedure_name' => $name,
            'scope' => $scope,
            'tooth_fdi' => $target->tooth?->fdi,
            'surfaces' => $target->surfaceCodes(),
            'jaw' => $target->jaw?->value,
            'quantity' => $quantity,
            'unit_price_amount' => $unitPriceAmount,
            'notes' => $notes,
            'performed_by' => $actorId,
            'performed_at' => $at,
            'created_by' => $actorId,
        ])->save();

        return $row->id;
    }

    public function procedures(string $encounterId): array
    {
        return array_values(EncounterProcedureModel::query()->where('encounter_id', $encounterId)->orderBy('performed_at')->orderBy('id')->get()
            ->map(fn (EncounterProcedureModel $p) => $this->procedure($p))->all());
    }

    public function findProcedure(string $encounterId, string $id): ?EncounterProcedureRecord
    {
        $row = EncounterProcedureModel::query()->where('encounter_id', $encounterId)->find($id);

        return $row === null ? null : $this->procedure($row);
    }

    public function removeProcedure(string $actorId, string $encounterId, string $id): void
    {
        $row = EncounterProcedureModel::query()->where('encounter_id', $encounterId)->findOrFail($id);
        $row->forceFill(['updated_by' => $actorId])->save();
        $row->delete();
    }

    private function record(EncounterModel $e): EncounterRecord
    {
        return new EncounterRecord(
            $e->id,
            $e->patient_id,
            $e->appointment_id,
            $e->doctor_id,
            $e->chair_id,
            $e->started_at->toDateTimeImmutable(),
            $e->closed_at?->toDateTimeImmutable(),
            EncounterStatus::from($e->status),
            $e->chief_complaint,
            $e->complaint_history,
            Vitals::fromArray($e->vitals),
            $e->anamnesis_at?->toDateTimeImmutable(),
            $e->version,
        );
    }

    private function note(ClinicalNoteModel $n): NoteRecord
    {
        return new NoteRecord($n->id, $n->encounter_id, NoteType::from($n->type), $n->section === null ? null : NoteSection::from($n->section), $n->body, $n->author_id, $n->authored_at->toDateTimeImmutable(), $n->supersedes_id, $n->reason);
    }

    private function diagnosis(EncounterDiagnosisModel $d): EncounterDiagnosisRecord
    {
        return new EncounterDiagnosisRecord($d->id, $d->encounter_id, $d->diagnosis_id, $d->icd10_code, $d->diagnosis_name, $d->tooth_fdi, $d->notes);
    }

    private function procedure(EncounterProcedureModel $p): EncounterProcedureRecord
    {
        return new EncounterProcedureRecord($p->id, $p->encounter_id, $p->procedure_id, $p->procedure_code, $p->procedure_name, $p->scope, $p->tooth_fdi, $p->surfaces, $p->jaw, $p->quantity, $p->unit_price_amount, $p->notes, $p->performed_by, $p->performed_at->toDateTimeImmutable());
    }
}
