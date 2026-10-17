<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use DateTimeImmutable;
use Src\Clinical\Domain\ClinicalFileRecord;
use Src\Clinical\Domain\ClinicalFileRepository;
use Src\Clinical\Domain\FileKind;
use Src\Clinical\Infrastructure\Eloquent\ClinicalFileModel;

final class EloquentClinicalFileRepository implements ClinicalFileRepository
{
    public function add(string $actorId, string $patientId, string $encounterId, ?int $toothFdi, string $path, string $mime, int $size, FileKind $kind, bool $encrypted, ?string $caption, DateTimeImmutable $at): string
    {
        $row = new ClinicalFileModel;
        $row->forceFill([
            'patient_id' => $patientId,
            'encounter_id' => $encounterId,
            'tooth_fdi' => $toothFdi,
            'disk' => 'private',
            'path' => $path,
            'mime' => $mime,
            'size' => $size,
            'kind' => $kind->value,
            'encrypted' => $encrypted,
            'caption' => $caption,
            'uploaded_by' => $actorId,
            'created_by' => $actorId,
            'created_at' => $at,
        ])->save();

        return $row->id;
    }

    public function find(string $id): ?ClinicalFileRecord
    {
        $row = ClinicalFileModel::query()->find($id);

        return $row === null ? null : $this->record($row);
    }

    public function forEncounter(string $encounterId): array
    {
        return array_values(ClinicalFileModel::query()->where('encounter_id', $encounterId)->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (ClinicalFileModel $f) => $this->record($f))->all());
    }

    public function forEncounters(array $encounterIds): array
    {
        if ($encounterIds === []) {
            return [];
        }
        $out = [];
        foreach (ClinicalFileModel::query()->whereIn('encounter_id', $encounterIds)->orderBy('created_at')->orderBy('id')->get() as $f) {
            $out[$f->encounter_id][] = $this->record($f);
        }

        return $out;
    }

    public function remove(string $actorId, string $id): void
    {
        $row = ClinicalFileModel::query()->findOrFail($id);
        $row->forceFill(['updated_by' => $actorId])->save();
        $row->delete();
    }

    private function record(ClinicalFileModel $f): ClinicalFileRecord
    {
        return new ClinicalFileRecord($f->id, $f->patient_id, $f->encounter_id, $f->tooth_fdi, $f->path, $f->mime, $f->size, FileKind::from($f->kind), $f->encrypted, $f->caption, $f->uploaded_by, $f->created_at->toDateTimeImmutable());
    }
}
