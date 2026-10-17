<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use Src\Clinic\Contracts\ProcedureCatalog;
use Src\Clinical\Application\Diagnosis\DiagnosisCatalog;
use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\Events\ProcedurePerformed;
use Src\Clinical\Domain\Events\ProcedureRetracted;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Clinical\Domain\ProcedureTarget;
use Src\Clinical\Domain\Tooth;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Transaction;

/**
 * RM-04 dan RM-05: diagnosa (ICD-10, boleh lebih dari satu, dapat ditautkan ke gigi) dan tindakan dari katalog. Tindakan
 * menyalin kode, nama, dan tarif katalog saat dicatat (MST-08) lalu memberi tahu Billing lewat event (KSR-01). Hanya
 * kunjungan yang masih berjalan menerima entri baru atau pembatalan; setelah ditutup hanya addendum (RM-07).
 */
final readonly class EncounterEntries
{
    public const MAX_QUANTITY = 99;

    public function __construct(
        private EncounterRepository $encounters,
        private EncounterGuard $guard,
        private DiagnosisCatalog $diagnoses,
        private ProcedureCatalog $procedures,
        private Transaction $tx,
        private AuditLogger $audit,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function addDiagnosis(string $actorId, string $encounterId, string $diagnosisId, ?int $toothFdi, ?string $notes): string
    {
        return $this->tx->run(function () use ($actorId, $encounterId, $diagnosisId, $toothFdi, $notes): string {
            $encounter = $this->guard->load($encounterId);
            $this->guard->assertOpen($encounter);

            $diagnosis = $this->diagnoses->find($diagnosisId)
                ?? throw new InvalidClinicalData('clinical.diagnosis_not_found', 'Diagnosa tidak ditemukan atau sudah nonaktif.');
            $tooth = $toothFdi === null ? null : (new Tooth($toothFdi))->fdi;
            foreach ($this->encounters->diagnoses($encounterId) as $existing) {
                if ($existing->diagnosisId === $diagnosisId && $existing->toothFdi === $tooth) {
                    throw new InvalidClinicalData('clinical.diagnosis_duplicate', 'Diagnosa itu sudah dicatat pada gigi yang sama.');
                }
            }

            $id = $this->encounters->addDiagnosis($actorId, $encounterId, $diagnosis->id, $diagnosis->code, $diagnosis->name, $tooth, $this->clean($notes));
            $this->audit->record(new AuditEntry($actorId, 'encounter.diagnosis_added', 'encounter', $encounterId, null, ['entry_id' => $id, 'icd10_code' => $diagnosis->code, 'tooth_fdi' => $tooth]));

            return $id;
        });
    }

    public function removeDiagnosis(string $actorId, string $encounterId, string $entryId): void
    {
        $this->tx->run(function () use ($actorId, $encounterId, $entryId): void {
            $encounter = $this->guard->load($encounterId);
            $this->guard->assertOpen($encounter);
            $entry = $this->encounters->findDiagnosis($encounterId, $entryId)
                ?? throw new InvalidClinicalData('clinical.entry_not_found', 'Diagnosa tidak ditemukan pada kunjungan ini.');

            $this->encounters->removeDiagnosis($actorId, $encounterId, $entryId);
            $this->audit->record(new AuditEntry($actorId, 'encounter.diagnosis_removed', 'encounter', $encounterId, ['entry_id' => $entryId, 'icd10_code' => $entry->icd10Code, 'tooth_fdi' => $entry->toothFdi], null));
        });
    }

    /**
     * @param  list<string>  $surfaceCodes
     */
    public function addProcedure(
        string $actorId,
        string $encounterId,
        string $procedureId,
        ?int $toothFdi,
        array $surfaceCodes,
        ?string $jaw,
        int $quantity,
        ?string $notes,
    ): string {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidClinicalData('clinical.invalid_quantity', 'Jumlah tindakan harus antara 1 dan '.self::MAX_QUANTITY.'.');
        }

        [$id, $event] = $this->tx->run(function () use ($actorId, $encounterId, $procedureId, $toothFdi, $surfaceCodes, $jaw, $quantity, $notes): array {
            $encounter = $this->guard->load($encounterId);
            $this->guard->assertOpen($encounter);

            $procedure = $this->procedures->find($procedureId)
                ?? throw new InvalidClinicalData('clinical.procedure_not_found', 'Tindakan tidak ditemukan atau sudah nonaktif.');
            /** @var 'tooth'|'surface'|'jaw'|'visit' $scope */
            $scope = $procedure->scope;
            $target = ProcedureTarget::for($scope, $toothFdi, $surfaceCodes, $jaw);
            $now = $this->clock->now();

            $id = $this->encounters->addProcedure($actorId, $encounterId, $procedure->id, $procedure->code, $procedure->name, $scope, $target, $quantity, $procedure->priceAmount, $this->clean($notes), $now);
            $this->audit->record(new AuditEntry($actorId, 'encounter.procedure_added', 'encounter', $encounterId, null, [
                'entry_id' => $id,
                'code' => $procedure->code,
                'quantity' => $quantity,
                'unit_price_amount' => $procedure->priceAmount,
                'tooth_fdi' => $target->tooth?->fdi,
            ]));

            return [$id, new ProcedurePerformed($encounterId, $id, $encounter->patientId, $procedure->code, $procedure->name, $quantity, $procedure->priceAmount, $now)];
        });

        $this->events->dispatch($event);

        return $id;
    }

    public function removeProcedure(string $actorId, string $encounterId, string $entryId): void
    {
        $event = $this->tx->run(function () use ($actorId, $encounterId, $entryId): ProcedureRetracted {
            $encounter = $this->guard->load($encounterId);
            $this->guard->assertOpen($encounter);
            $entry = $this->encounters->findProcedure($encounterId, $entryId)
                ?? throw new InvalidClinicalData('clinical.entry_not_found', 'Tindakan tidak ditemukan pada kunjungan ini.');

            $this->encounters->removeProcedure($actorId, $encounterId, $entryId);
            $this->audit->record(new AuditEntry($actorId, 'encounter.procedure_removed', 'encounter', $encounterId, ['entry_id' => $entryId, 'code' => $entry->code, 'quantity' => $entry->quantity, 'unit_price_amount' => $entry->unitPriceAmount], null));

            return new ProcedureRetracted($encounterId, $entryId, $this->clock->now());
        });

        $this->events->dispatch($event);
    }

    private function clean(?string $text): ?string
    {
        $text = $text === null ? null : trim($text);
        if ($text === null || $text === '') {
            return null;
        }
        if (mb_strlen($text) > 500) {
            throw new InvalidClinicalData('clinical.text_too_long', 'Catatan terlalu panjang (maksimal 500 karakter).');
        }

        return $text;
    }
}
