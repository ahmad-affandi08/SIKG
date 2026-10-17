<?php

declare(strict_types=1);

namespace Src\Patient\Application\Merge;

use Src\Patient\Domain\InvalidPatientData;
use Src\Patient\Domain\PatientNotFound;
use Src\Patient\Domain\PatientRecord;
use Src\Patient\Domain\PatientRepository;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\PatientReferenceMover;
use Src\Shared\Application\Transaction;

/**
 * PAS-07: Admin menggabungkan pasien ganda ke satu pasien utama. Semua data milik pasien ganda (janji, kunjungan, odontogram,
 * berkas, resep, tagihan, riwayat medis, persetujuan, wali) berpindah ke pasien utama dalam satu transaksi; pasien ganda
 * diarsipkan dengan penunjuk ke pasien utama, tidak dihapus (M5). Tercatat di audit log (M10).
 */
final readonly class MergePatients
{
    public const MIN_REASON = 5;

    /**
     * @param  iterable<PatientReferenceMover>  $movers
     */
    public function __construct(
        private PatientRepository $patients,
        private iterable $movers,
        private Transaction $tx,
        private AuditLogger $audit,
        private AccessLogger $access,
        private Clock $clock,
    ) {}

    /**
     * Pratinjau penggabungan: kedua pasien, jumlah data yang akan berpindah, peringatan, dan penghalang.
     *
     * @return array{target: PatientRecord, source: PatientRecord, counts: list<array{label: string, count: int}>, warnings: list<string>, blocker: string|null}
     */
    public function preview(string $targetId, string $sourceId): array
    {
        $target = $this->patients->find($targetId) ?? throw new PatientNotFound;
        $source = $this->patients->find($sourceId) ?? throw new PatientNotFound;
        $this->access->patientRecordOpened($targetId, 'patients.merge');
        $this->access->patientRecordOpened($sourceId, 'patients.merge');

        $counts = [];
        foreach ($this->movers as $mover) {
            $counts[] = ['label' => $mover->label(), 'count' => $mover->count($sourceId)];
        }
        $histories = count($this->patients->histories($sourceId));
        $counts[] = ['label' => 'Riwayat medis', 'count' => $histories];
        $counts[] = ['label' => 'Persetujuan data', 'count' => count($this->patients->consents($sourceId))];

        $warnings = [];
        if ($target->profile->birthDate->format('Y-m-d') !== $source->profile->birthDate->format('Y-m-d')) {
            $warnings[] = 'Tanggal lahir kedua pasien berbeda.';
        }
        if ($target->profile->sex !== $source->profile->sex) {
            $warnings[] = 'Jenis kelamin kedua pasien berbeda.';
        }
        if ($targetId === $sourceId) {
            $blocker = 'Pilih pasien ganda yang berbeda dari pasien utama.';
        } elseif ($target->profile->nik !== null && $source->profile->nik !== null && $target->profile->nik->value !== $source->profile->nik->value) {
            $blocker = 'NIK kedua pasien berbeda, jadi keduanya kemungkinan orang yang berbeda dan tidak dapat digabung.';
        } else {
            $blocker = null;
        }

        return ['target' => $target, 'source' => $source, 'counts' => $counts, 'warnings' => $warnings, 'blocker' => $blocker];
    }

    /**
     * @return array<string, mixed> ringkasan data yang berpindah
     */
    public function __invoke(string $actorId, string $targetId, string $sourceId, string $reason): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < self::MIN_REASON) {
            throw new InvalidPatientData('patient.merge_reason_required', 'Alasan penggabungan wajib diisi (minimal '.self::MIN_REASON.' karakter).');
        }

        return $this->tx->run(function () use ($actorId, $targetId, $sourceId, $reason): array {
            $target = $this->patients->find($targetId) ?? throw new PatientNotFound;
            $source = $this->patients->find($sourceId) ?? throw new PatientNotFound;

            $moved = [];
            foreach ($this->movers as $mover) {
                $moved[$mover->label()] = $mover->move($sourceId, $targetId);
            }
            $own = $this->patients->absorb($actorId, $sourceId, $targetId, $this->clock->now());

            $after = [
                'source_id' => $sourceId,
                'source_medical_record_no' => $source->medicalRecordNo,
                'reason' => $reason,
                'moved' => $moved,
                'histories' => $own['histories'],
                'consents' => $own['consents'],
                'guardian' => $own['guardian'],
                'filled' => $own['filled'],
            ];
            $this->audit->record(new AuditEntry($actorId, 'patient.merged', 'patient', $targetId, ['medical_record_no' => $target->medicalRecordNo], $after));
            $this->audit->record(new AuditEntry($actorId, 'patient.merged_into', 'patient', $sourceId, ['medical_record_no' => $source->medicalRecordNo], ['target_id' => $targetId, 'target_medical_record_no' => $target->medicalRecordNo]));

            return $after;
        });
    }
}
