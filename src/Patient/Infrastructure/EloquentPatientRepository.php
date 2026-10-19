<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Src\IdentityAccess\Contracts\UserLookup;
use Src\Patient\Domain\ConsentRecord;
use Src\Patient\Domain\DuplicateCandidate;
use Src\Patient\Domain\EmergencyContact;
use Src\Patient\Domain\Guardian;
use Src\Patient\Domain\HistoryRecord;
use Src\Patient\Domain\InvalidPatientData;
use Src\Patient\Domain\MedicalHistoryEntry;
use Src\Patient\Domain\MedicalHistoryType;
use Src\Patient\Domain\NameKey;
use Src\Patient\Domain\Nik;
use Src\Patient\Domain\PatientNotFound;
use Src\Patient\Domain\PatientProfile;
use Src\Patient\Domain\PatientRecord;
use Src\Patient\Domain\PatientRepository;
use Src\Patient\Domain\PatientSummary;
use Src\Patient\Domain\PhoneNumber;
use Src\Patient\Domain\Severity;
use Src\Patient\Domain\Sex;
use Src\Patient\Infrastructure\Eloquent\PatientConsentModel;
use Src\Patient\Infrastructure\Eloquent\PatientGuardianModel;
use Src\Patient\Infrastructure\Eloquent\PatientMedicalHistoryModel;
use Src\Patient\Infrastructure\Eloquent\PatientModel;
use Src\Shared\Application\Clock;
use Src\Shared\Application\PiiProtector;

final readonly class EloquentPatientRepository implements PatientRepository
{
    public function __construct(private PiiProtector $pii, private Clock $clock, private UserLookup $users) {}

    public function add(string $actorId, string $medicalRecordNo, PatientProfile $profile): string
    {
        $patient = new PatientModel;
        $patient->forceFill($this->attributes($profile) + ['medical_record_no' => $medicalRecordNo, 'created_by' => $actorId])->save();
        $this->syncGuardian($actorId, $patient->id, $profile->guardian);

        return $patient->id;
    }

    public function update(string $actorId, string $id, PatientProfile $profile): void
    {
        $patient = PatientModel::query()->lockForUpdate()->find($id) ?? throw new PatientNotFound;
        $patient->forceFill($this->attributes($profile) + ['updated_by' => $actorId])->save();
        $this->syncGuardian($actorId, $id, $profile->guardian);
    }

    public function find(string $id): ?PatientRecord
    {
        $patient = PatientModel::query()->find($id);
        if ($patient === null) {
            return null;
        }
        $guardian = PatientGuardianModel::query()->where('patient_id', $id)->first();

        return new PatientRecord(
            $patient->id,
            $patient->medical_record_no,
            $this->profile($patient, $guardian),
            $patient->data_consent_at === null ? null : $patient->data_consent_at->toDateTimeImmutable(),
        );
    }

    public function archive(string $actorId, string $id): void
    {
        $patient = PatientModel::query()->lockForUpdate()->find($id) ?? throw new PatientNotFound;
        $patient->forceFill(['updated_by' => $actorId])->save();
        $patient->delete();
    }

    public function search(string $term, int $limit): array
    {
        $term = trim($term);
        $query = PatientModel::query();
        $like = addcslashes($term, '\\%_');
        $nikHash = Nik::looksLikeNik($term) ? $this->pii->blindIndex('nik', (string) preg_replace('/\s+/', '', $term)) : null;
        $phone = preg_match('/^[+\d][\d\s\-()]*$/', $term) === 1 ? PhoneNumber::tryFrom($term) : null;
        $tokens = preg_match('/\p{L}/u', $term) === 1 ? array_filter(explode(' ', NameKey::of($term))) : [];

        $query->where(function (Builder $q) use ($like, $nikHash, $phone, $tokens): void {
            $q->where('medical_record_no', 'like', $like.'%');
            if ($nikHash !== null) {
                $q->orWhere('nik_hash', $nikHash);
            }
            if ($phone !== null) {
                $q->orWhere('phone_hash', $this->pii->blindIndex('phone', $phone->value));
            }
            if ($tokens !== []) {
                $q->orWhere(function (Builder $names) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $escaped = addcslashes($token, '\\%_');
                        $names->where(fn (Builder $one) => $one->where('name_search', 'like', $escaped.'%')->orWhere('name_search', 'like', '% '.$escaped.'%'));
                    }
                });
            }
        });

        return array_values($query->orderBy('name_search')->limit($limit)->get()->map(fn (PatientModel $p) => new PatientSummary(
            $p->id,
            $p->medical_record_no,
            $p->name,
            $p->birth_date->toDateString(),
            $p->sex,
            $p->phone_encrypted === null ? null : (PhoneNumber::tryFrom($this->pii->decrypt($p->phone_encrypted))?->masked()),
            $p->nik_encrypted === null ? null : $this->pii->decrypt($p->nik_encrypted),
        ))->all());
    }

    public function findDuplicates(PatientProfile $profile, ?string $ignoreId = null): array
    {
        $found = [];
        $phoneHash = $profile->phone === null ? null : $this->pii->blindIndex('phone', $profile->phone->value);

        if ($profile->nik !== null) {
            $byNik = PatientModel::query()->where('nik_hash', $this->pii->blindIndex('nik', $profile->nik->value))
                ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))->limit(10)->get();
            foreach ($byNik as $p) {
                $found[$p->id] = $this->candidate($p, 'nik');
            }
        }

        $sameBirth = PatientModel::query()->where('birth_date', $profile->birthDate->format('Y-m-d'))
            ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))->limit(200)->get();
        foreach ($sameBirth as $p) {
            if (isset($found[$p->id])) {
                continue;
            }
            $samePhone = $phoneHash !== null && $this->phoneHashOf($p) === $phoneHash;
            if ($samePhone || NameKey::similar($p->name, $profile->name)) {
                $found[$p->id] = $this->candidate($p, 'similar');
            }
        }

        return array_values($found);
    }

    public function absorb(string $actorId, string $sourceId, string $targetId, DateTimeImmutable $at): array
    {
        if ($sourceId === $targetId) {
            throw new InvalidPatientData('patient.merge_same', 'Pilih pasien ganda yang berbeda dari pasien utama.');
        }
        // Kunci berurutan menurut id agar dua penggabungan bersamaan tidak saling menunggu.
        $rows = PatientModel::query()->whereIn('id', [$sourceId, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $source = $rows->get($sourceId);
        $target = $rows->get($targetId);
        if (! $source instanceof PatientModel || ! $target instanceof PatientModel) {
            throw new PatientNotFound;
        }
        if ($source->nik_hash !== null && $target->nik_hash !== null && $source->nik_hash !== $target->nik_hash) {
            throw new InvalidPatientData('patient.merge_nik_conflict', 'NIK kedua pasien berbeda, jadi keduanya kemungkinan orang yang berbeda dan tidak dapat digabung.');
        }

        $filled = [];
        $fill = [];
        if ($target->nik_hash === null && $source->nik_hash !== null) {
            $fill += ['nik_encrypted' => $source->nik_encrypted, 'nik_hash' => $source->nik_hash, 'nik_missing_reason' => null];
            $filled[] = 'NIK';
        }
        if ($target->phone_hash === null && $source->phone_hash !== null) {
            $fill += ['phone_encrypted' => $source->phone_encrypted, 'phone_hash' => $source->phone_hash];
            $filled[] = 'Nomor HP';
        }
        foreach (['address' => 'Alamat', 'occupation' => 'Pekerjaan', 'emergency_contact' => 'Kontak darurat'] as $column => $label) {
            if (($target->{$column} === null || $target->{$column} === '') && $source->{$column} !== null && $source->{$column} !== '') {
                $fill[$column] = $source->{$column};
                $filled[] = $label;
            }
        }
        if ($target->data_consent_at === null && $source->data_consent_at !== null) {
            $fill['data_consent_at'] = $source->data_consent_at;
            $filled[] = 'Persetujuan data';
        }
        if ($fill !== []) {
            $target->forceFill($fill + ['updated_by' => $actorId])->save();
        }

        $existing = [];
        foreach (PatientMedicalHistoryModel::query()->where('patient_id', $targetId)->get() as $h) {
            $existing[$h->type.'|'.mb_strtolower($h->name)] = true;
        }
        $histories = 0;
        foreach (PatientMedicalHistoryModel::query()->where('patient_id', $sourceId)->get() as $h) {
            if (isset($existing[$h->type.'|'.mb_strtolower($h->name)])) {
                continue;
            }
            $h->forceFill(['patient_id' => $targetId, 'updated_by' => $actorId])->save();
            $histories++;
        }

        $consents = PatientConsentModel::query()->where('patient_id', $sourceId)->update(['patient_id' => $targetId]);

        $guardian = false;
        if (! PatientGuardianModel::withTrashed()->where('patient_id', $targetId)->exists()) {
            $guardian = PatientGuardianModel::query()->where('patient_id', $sourceId)->update(['patient_id' => $targetId]) > 0;
        }

        $source->forceFill(['merged_into_id' => $targetId, 'merged_at' => $at, 'updated_by' => $actorId])->save();
        $source->delete();

        return ['histories' => $histories, 'consents' => $consents, 'guardian' => $guardian, 'filled' => $filled];
    }

    public function addHistory(string $actorId, string $patientId, MedicalHistoryEntry $entry): string
    {
        $row = new PatientMedicalHistoryModel;
        $row->forceFill([
            'patient_id' => $patientId,
            'type' => $entry->type->value,
            'name' => $entry->name,
            'severity' => $entry->severity?->value,
            'notes' => $entry->notes,
            'created_by' => $actorId,
        ])->save();

        return $row->id;
    }

    public function removeHistory(string $actorId, string $patientId, string $entryId): MedicalHistoryEntry
    {
        $row = PatientMedicalHistoryModel::query()->where('patient_id', $patientId)->find($entryId) ?? throw new PatientNotFound;
        $entry = $this->entry($row);
        $row->forceFill(['updated_by' => $actorId])->save();
        $row->delete();

        return $entry;
    }

    public function histories(string $patientId): array
    {
        return array_values(PatientMedicalHistoryModel::query()->where('patient_id', $patientId)->orderBy('type')->orderBy('name')->get()
            ->map(fn (PatientMedicalHistoryModel $h) => new HistoryRecord($h->id, $this->entry($h)))->all());
    }

    public function recordConsent(string $actorId, string $patientId, string $version, DateTimeImmutable $givenAt): void
    {
        $consent = new PatientConsentModel;
        $consent->forceFill(['patient_id' => $patientId, 'text_version' => $version, 'given_at' => $givenAt, 'recorded_by' => $actorId])->save();
        PatientModel::query()->whereKey($patientId)->update(['data_consent_at' => $givenAt]);
    }

    public function consents(string $patientId): array
    {
        $rows = PatientConsentModel::query()->where('patient_id', $patientId)->orderByDesc('given_at')->get();
        $names = $this->users->names(array_values(array_unique(array_filter($rows->map(fn (PatientConsentModel $c) => $c->recorded_by)->all()))));

        return array_values($rows->map(fn (PatientConsentModel $c) => new ConsentRecord(
            $c->text_version,
            $c->given_at->toDateTimeImmutable(),
            $names[$c->recorded_by ?? ''] ?? '-',
        ))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(PatientProfile $profile): array
    {
        $emergency = $profile->emergencyContact === null ? null : json_encode([
            'name' => $profile->emergencyContact->name,
            'relation' => $profile->emergencyContact->relation,
            'phone' => $profile->emergencyContact->phone->value,
        ], JSON_THROW_ON_ERROR);

        return [
            'name' => $profile->name,
            'name_search' => NameKey::of($profile->name),
            'nik_encrypted' => $profile->nik === null ? null : $this->pii->encrypt($profile->nik->value),
            'nik_hash' => $profile->nik === null ? null : $this->pii->blindIndex('nik', $profile->nik->value),
            'nik_missing_reason' => $profile->nikMissingReason,
            'birth_date' => $profile->birthDate->format('Y-m-d'),
            'sex' => $profile->sex->value,
            'address' => $profile->address,
            'phone_encrypted' => $profile->phone === null ? null : $this->pii->encrypt($profile->phone->value),
            'phone_hash' => $profile->phone === null ? null : $this->pii->blindIndex('phone', $profile->phone->value),
            'occupation' => $profile->occupation,
            'emergency_contact' => $emergency === null ? null : $this->pii->encrypt($emergency),
        ];
    }

    private function syncGuardian(string $actorId, string $patientId, ?Guardian $guardian): void
    {
        $row = PatientGuardianModel::withTrashed()->where('patient_id', $patientId)->first();

        if ($guardian === null) {
            $row?->delete();

            return;
        }

        $row ??= new PatientGuardianModel;
        $row->forceFill([
            'patient_id' => $patientId,
            'name' => $guardian->name,
            'relation' => $guardian->relation,
            'phone_encrypted' => $guardian->phone === null ? null : $this->pii->encrypt($guardian->phone->value),
            $row->exists ? 'updated_by' : 'created_by' => $actorId,
            'deleted_at' => null,
        ])->save();
    }

    private function profile(PatientModel $p, ?PatientGuardianModel $g): PatientProfile
    {
        $emergency = null;
        if ($p->emergency_contact !== null) {
            /** @var array{name: string, relation: string, phone: string} $data */
            $data = json_decode($this->pii->decrypt($p->emergency_contact), true, 512, JSON_THROW_ON_ERROR);
            $emergency = new EmergencyContact($data['name'], $data['relation'], new PhoneNumber($data['phone']));
        }
        $guardian = $g === null ? null : new Guardian($g->name, $g->relation, $g->phone_encrypted === null ? null : new PhoneNumber($this->pii->decrypt($g->phone_encrypted)));

        // `today` = tanggal data dimuat: memuat pasien yang sudah tersimpan tidak boleh gagal oleh aturan umur.
        return new PatientProfile(
            name: $p->name,
            nik: $p->nik_encrypted === null ? null : new Nik($this->pii->decrypt($p->nik_encrypted)),
            nikMissingReason: $p->nik_missing_reason,
            birthDate: $p->birth_date->toDateTimeImmutable(),
            sex: Sex::from($p->sex),
            address: $p->address,
            phone: $p->phone_encrypted === null ? null : new PhoneNumber($this->pii->decrypt($p->phone_encrypted)),
            occupation: $p->occupation,
            emergencyContact: $emergency,
            guardian: $guardian,
            today: $this->clock->now(),
            validate: false,
        );
    }

    private function candidate(PatientModel $p, string $reason): DuplicateCandidate
    {
        return new DuplicateCandidate($p->id, $p->medical_record_no, $p->name, $p->birth_date->toDateString(), $reason);
    }

    private function phoneHashOf(PatientModel $p): ?string
    {
        return $p->phone_encrypted === null ? null : $this->pii->blindIndex('phone', $this->pii->decrypt($p->phone_encrypted));
    }

    private function entry(PatientMedicalHistoryModel $h): MedicalHistoryEntry
    {
        return new MedicalHistoryEntry(MedicalHistoryType::from($h->type), $h->name, $h->severity === null ? null : Severity::from($h->severity), $h->notes);
    }
}
