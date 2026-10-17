<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinic\Contracts\DocumentTemplateCatalog;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Clinical\Domain\DocumentDefaults;
use Src\Clinical\Domain\DocumentRecord;
use Src\Clinical\Domain\DocumentRepository;
use Src\Clinical\Domain\DocumentType;
use Src\Clinical\Domain\EncounterNotFound;
use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\SequenceGenerator;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\NumberPattern;

/**
 * RM-09 dan RM-10: informed consent per jenis tindakan, surat keterangan, dan surat rujukan, dicetak untuk tanda tangan basah.
 * Penanda templat diisi di layar (pratinjau langsung) dan dokter menyunting teks akhirnya; yang disimpan dan dicetak adalah
 * salinan teks akhir itu beserta nama dan SIP dokter, sehingga cetak ulang selalu sama dan tidak berubah bila templat atau
 * data dokter berubah (seperti tarif di tagihan, MST-08). Dokumen yang sudah terbit hanya-tambah; koreksi berarti menerbitkan baru.
 */
final readonly class ClinicalDocuments
{
    public const MIN_BODY = 20;

    public const MAX_BODY = 8000;

    public function __construct(
        private DocumentRepository $documents,
        private EncounterRepository $encounters,
        private DocumentTemplateCatalog $templates,
        private PatientLookup $patients,
        private DoctorDirectory $doctors,
        private LetterheadProvider $letterhead,
        private ClinicCalendar $calendar,
        private SequenceGenerator $sequences,
        private TenantContext $tenant,
        private Transaction $tx,
        private AuditLogger $audit,
        private AccessLogger $access,
        private Clock $clock,
    ) {}

    /**
     * @return array{patient: array{id: string, name: string, medical_record_no: string}, documents: list<array<string, mixed>>}
     */
    public function overview(string $patientId, string $route): array
    {
        $patient = $this->patients->summaries([$patientId])[$patientId] ?? throw new EncounterNotFound;
        $this->access->patientRecordOpened($patientId, $route);
        $zone = new DateTimeZone($this->calendar->timezone());

        return [
            'patient' => ['id' => $patient->id, 'name' => $patient->name, 'medical_record_no' => $patient->medicalRecordNo],
            'documents' => array_map(fn (DocumentRecord $d) => [
                'id' => $d->id,
                'type' => $d->type->value,
                'type_label' => $d->type->label(),
                'number' => $d->number,
                'title' => $d->title,
                'doctor_name' => $d->doctorName,
                'encounter_id' => $d->encounterId,
                'issued_at' => $d->issuedAt->setTimezone($zone)->format('Y-m-d H:i'),
            ], $this->documents->forPatient($patientId)),
        ];
    }

    /**
     * Bahan formulir: data untuk mengisi penanda, daftar templat (bawaan dan buatan Admin), dokter, dan kunjungan pasien.
     *
     * @return array<string, mixed>
     */
    public function formData(string $patientId, DocumentType $type, string $route): array
    {
        $patient = $this->patients->summaries([$patientId])[$patientId] ?? throw new EncounterNotFound;
        $this->access->patientRecordOpened($patientId, $route);
        $now = $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()));

        $templates = [['id' => '', 'name' => 'Templat bawaan', 'procedure_id' => null, 'body' => DocumentDefaults::body($type)]];
        foreach ($this->templates->active($type->value) as $template) {
            $templates[] = $template->toArray();
        }

        $encounters = [];
        $doctorNames = [];
        $records = $this->encounters->forPatient($patientId);
        foreach ($this->doctors->byIds(array_values(array_unique(array_map(fn ($e) => $e->doctorId, $records)))) as $doctor) {
            $doctorNames[$doctor->id] = $doctor->name;
        }
        foreach ($records as $encounter) {
            $encounters[] = [
                'id' => $encounter->id,
                'date' => $encounter->startedAt->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d'),
                'doctor_id' => $encounter->doctorId,
                'doctor_name' => $doctorNames[$encounter->doctorId] ?? '-',
                'diagnoses' => implode('; ', array_map(fn ($d) => $d->icd10Code.' '.$d->name, $this->encounters->diagnoses($encounter->id))),
            ];
        }

        return [
            'type' => ['value' => $type->value, 'label' => $type->label()],
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'medical_record_no' => $patient->medicalRecordNo,
                'birth_date' => $patient->birthDate,
                'age' => $patient->birthDate === null ? null : (new DateTimeImmutable($patient->birthDate))->diff($now)->y,
            ],
            'clinic_name' => $this->letterhead->forDocument()->clinicName,
            'today' => $now->format('Y-m-d'),
            'templates' => $templates,
            'doctors' => array_map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'sip' => $d->sipNo], $this->doctors->active()),
            'encounters' => $encounters,
        ];
    }

    public function issue(string $actorId, string $patientId, DocumentType $type, ?string $templateId, ?string $encounterId, string $doctorId, ?string $title, string $body): string
    {
        $body = trim(str_replace("\r\n", "\n", $body));
        if (mb_strlen($body) < self::MIN_BODY || mb_strlen($body) > self::MAX_BODY) {
            throw new InvalidClinicalData('document.body_invalid', 'Isi dokumen harus antara '.self::MIN_BODY.' dan '.self::MAX_BODY.' karakter.');
        }
        if (preg_match('/\{\{.*?\}\}/s', $body) === 1) {
            throw new InvalidClinicalData('document.placeholder_left', 'Masih ada penanda {{...}} yang belum terisi. Lengkapi isiannya atau hapus kalimatnya.');
        }

        return $this->tx->run(function () use ($actorId, $patientId, $type, $templateId, $encounterId, $doctorId, $title, $body): string {
            if (! $this->patients->exists($patientId)) {
                throw new EncounterNotFound;
            }
            $doctor = $this->doctors->find($doctorId);
            if ($doctor === null || ! $doctor->active) {
                throw new InvalidClinicalData('document.doctor_invalid', 'Dokter tidak ditemukan atau sudah nonaktif.');
            }
            if ($encounterId !== null) {
                $encounter = $this->encounters->find($encounterId);
                if ($encounter === null || $encounter->patientId !== $patientId) {
                    throw new InvalidClinicalData('document.encounter_invalid', 'Kunjungan itu bukan milik pasien ini.');
                }
            }
            if ($templateId !== null) {
                $template = $this->templates->find($templateId);
                if ($template === null || $template->type !== $type->value) {
                    throw new InvalidClinicalData('document.template_invalid', 'Templat tidak ditemukan atau bukan untuk jenis dokumen ini.');
                }
            }

            $now = $this->clock->now();
            $number = $this->nextNumber($type, $now);
            $heading = trim((string) $title) === '' ? $type->label() : mb_substr(trim((string) $title), 0, 150);
            $id = $this->documents->add($actorId, $patientId, $encounterId, $type, $templateId, $number, $heading, $body, $doctor->id, $doctor->name, $doctor->sipNo, $now);

            $this->audit->record(new AuditEntry($actorId, 'document.issued', 'clinical_document', $id, null, [
                'type' => $type->value, 'number' => $number, 'patient_id' => $patientId, 'encounter_id' => $encounterId, 'template_id' => $templateId,
            ]));

            return $id;
        });
    }

    /**
     * Data cetak dari salinan yang tersimpan. Mencetak tercatat di access log dan audit (M10).
     *
     * @return array<string, mixed>
     */
    public function printData(string $actorId, string $documentId): array
    {
        $document = $this->documents->find($documentId) ?? throw new EncounterNotFound;
        $patient = $this->patients->summaries([$document->patientId])[$document->patientId] ?? throw new EncounterNotFound;
        $this->access->patientRecordOpened($document->patientId, 'documents.print');
        $this->audit->record(new AuditEntry($actorId, 'document.printed', 'clinical_document', $documentId, null, ['type' => $document->type->value, 'number' => $document->number]));
        $head = $this->letterhead->forDocument();
        $issued = $document->issuedAt->setTimezone(new DateTimeZone($this->calendar->timezone()));

        return [
            'letterhead' => $head->toArray(),
            'heading' => $document->type->heading(),
            'number' => $document->number,
            'title' => $document->title,
            'blocks' => self::blocks($document->body),
            'official_letter' => $document->type->isOfficialLetter(),
            'subject' => $document->type->subject(),
            'signer_role' => $document->type->signerRole(),
            'issued_on' => $issued->format('d/m/Y'),
            'place' => $head->city,
            'doctor' => ['name' => $document->doctorName, 'sip' => $document->doctorSip],
            'patient' => ['name' => $patient->name, 'medical_record_no' => $patient->medicalRecordNo],
            'patient_signature' => $document->type->needsPatientSignature(),
            'type' => $document->type->value,
            'file_name' => strtolower(str_replace('/', '-', $document->number)),
        ];
    }

    /**
     * Isi dokumen menjadi blok cetak: daftar "Label: isi" (tabel dua kolom), daftar bernomor, atau paragraf biasa.
     *
     * @return list<array{kind: 'kv'|'list'|'text', rows?: list<array{0: string, 1: string}>, items?: list<string>, text?: string}>
     */
    public static function blocks(string $body): array
    {
        $blocks = [];
        foreach (preg_split('/\n{2,}/', $body) ?: [] as $paragraph) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $paragraph)), fn (string $line) => $line !== ''));
            if ($lines === []) {
                continue;
            }
            $pairs = [];
            foreach ($lines as $line) {
                if (preg_match('/^([^:\n]{2,60}):\s*(.*)$/u', $line, $m) === 1) {
                    $pairs[] = [$m[1], $m[2]];
                }
            }
            $items = [];
            foreach ($lines as $line) {
                if (preg_match('/^\d+[.)]\s+(.+)$/u', $line, $m) === 1) {
                    $items[] = $m[1];
                }
            }

            $total = count($lines);
            if ($total >= 2 && count($pairs) === $total) {
                $blocks[] = ['kind' => 'kv', 'rows' => $pairs];
            } elseif ($total >= 2 && count($items) === $total) {
                $blocks[] = ['kind' => 'list', 'items' => $items];
            } else {
                $blocks[] = ['kind' => 'text', 'text' => implode("\n", $lines)];
            }
        }

        return $blocks;
    }

    /**
     * @return array{patient_id: string, type: string}
     */
    public function documentOf(string $documentId): array
    {
        $document = $this->documents->find($documentId) ?? throw new EncounterNotFound;

        return ['patient_id' => $document->patientId, 'type' => $document->type->value];
    }

    private function nextNumber(DocumentType $type, DateTimeImmutable $now): string
    {
        $pattern = new NumberPattern('{seq:3}/'.$type->numberPrefix().'/{rom}/{yyyy}');
        $timezone = $this->calendar->timezone();
        $scope = trim('clinic:'.$this->tenant->clinicId().':'.$type->value.':'.$pattern->scopeKey($now, $timezone), ':');

        return $pattern->render($this->sequences->next('document_no', $scope), $now, $timezone);
    }
}
