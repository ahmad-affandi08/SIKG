<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinical\Domain\ClinicalFileRepository;
use Src\Clinical\Domain\EncounterNotFound;
use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\NoteType;
use Src\Patient\Contracts\PatientLookup;
use Src\Pharmacy\Contracts\PrescriptionLookup;
use Src\Shared\Application\AccessLogger;

/**
 * RM-11: linimasa riwayat pasien. Seluruh kunjungan (terbaru dulu) beserta diagnosa, tindakan, ringkasan asesmen dan rencana,
 * jumlah addendum, dan berkas dalam satu halaman. Membuka linimasa tercatat di access log (M10).
 */
final readonly class PatientTimeline
{
    public function __construct(
        private EncounterRepository $encounters,
        private ClinicalFileRepository $files,
        private DoctorDirectory $doctors,
        private PatientLookup $patients,
        private AccessLogger $access,
        private PrescriptionLookup $prescriptions,
    ) {}

    /**
     * @return array{patient: array{id: string, name: string, medical_record_no: string}, encounters: list<array<string, mixed>>}
     */
    public function __invoke(string $patientId, string $route): array
    {
        $patient = $this->patients->summaries([$patientId])[$patientId] ?? throw new EncounterNotFound;
        $this->access->patientRecordOpened($patientId, $route);

        $encounters = $this->encounters->forPatient($patientId);
        $filesByEncounter = $this->files->forEncounters(array_map(fn ($e) => $e->id, $encounters));
        $prescriptionsByEncounter = $this->prescriptions->forEncounters(array_map(fn ($e) => $e->id, $encounters));
        $doctors = $this->doctors->byIds(array_values(array_unique(array_map(fn ($e) => $e->doctorId, $encounters))));

        $rows = [];
        foreach ($encounters as $encounter) {
            $notes = $this->encounters->currentNotes($encounter->id);
            $summary = fn (NoteType $type) => collect($notes)->first(fn ($n) => $n->type === $type)?->body;

            $rows[] = [
                'id' => $encounter->id,
                'started_at' => $encounter->startedAt->format('c'),
                'status' => $encounter->status->value,
                'status_label' => $encounter->status->label(),
                'doctor_name' => ($doctors[$encounter->doctorId] ?? null)?->name,
                'chief_complaint' => $encounter->chiefComplaint,
                'assessment' => $summary(NoteType::Assessment),
                'plan' => $summary(NoteType::Plan),
                'addendum_count' => count(array_filter($notes, fn ($n) => $n->type === NoteType::Addendum)),
                'diagnoses' => array_map(fn ($d) => $d->toArray(), $this->encounters->diagnoses($encounter->id)),
                'procedures' => array_map(fn ($p) => $p->toArray(), $this->encounters->procedures($encounter->id)),
                'prescriptions' => array_map(fn ($p) => $p->toArray(), array_values(array_filter($prescriptionsByEncounter[$encounter->id] ?? [], fn ($p) => $p->status === 'issued'))),
                'files' => array_map(fn ($f) => [
                    'id' => $f->id,
                    'kind' => $f->kind->value,
                    'kind_label' => $f->kind->label(),
                    'tooth_fdi' => $f->toothFdi,
                    'mime' => $f->mime,
                    'caption' => $f->caption,
                ], $filesByEncounter[$encounter->id] ?? []),
            ];
        }

        return ['patient' => ['id' => $patient->id, 'name' => $patient->name, 'medical_record_no' => $patient->medicalRecordNo], 'encounters' => $rows];
    }
}
