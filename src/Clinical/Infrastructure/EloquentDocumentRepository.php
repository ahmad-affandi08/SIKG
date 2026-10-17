<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use DateTimeImmutable;
use Src\Clinical\Domain\DocumentRecord;
use Src\Clinical\Domain\DocumentRepository;
use Src\Clinical\Domain\DocumentType;
use Src\Clinical\Infrastructure\Eloquent\ClinicalDocumentModel;

final class EloquentDocumentRepository implements DocumentRepository
{
    public function add(string $actorId, string $patientId, ?string $encounterId, DocumentType $type, ?string $templateId, string $number, string $title, string $body, string $doctorId, string $doctorName, ?string $doctorSip, DateTimeImmutable $at): string
    {
        $row = new ClinicalDocumentModel;
        $row->forceFill([
            'patient_id' => $patientId, 'encounter_id' => $encounterId, 'type' => $type->value, 'template_id' => $templateId, 'number' => $number, 'title' => $title,
            'body' => $body, 'doctor_id' => $doctorId, 'doctor_name' => $doctorName, 'doctor_sip' => $doctorSip, 'issued_by' => $actorId, 'issued_at' => $at, 'created_at' => $at,
        ])->save();

        return $row->id;
    }

    public function find(string $id): ?DocumentRecord
    {
        $row = ClinicalDocumentModel::query()->find($id);

        return $row instanceof ClinicalDocumentModel ? $this->record($row) : null;
    }

    public function forPatient(string $patientId): array
    {
        return array_values(ClinicalDocumentModel::query()->where('patient_id', $patientId)->orderByDesc('issued_at')->orderByDesc('id')->get()
            ->map(fn (ClinicalDocumentModel $d) => $this->record($d))->all());
    }

    private function record(ClinicalDocumentModel $d): DocumentRecord
    {
        return new DocumentRecord(
            $d->id, $d->patient_id, $d->encounter_id, DocumentType::from($d->type), $d->number, $d->title, $d->body,
            $d->doctor_id, $d->doctor_name, $d->doctor_sip, $d->issued_by, $d->issued_at->toDateTimeImmutable(),
        );
    }
}
