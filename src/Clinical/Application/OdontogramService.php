<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use Src\Clinic\Contracts\ToothConditionCatalog;
use Src\Clinical\Domain\EncounterNotFound;
use Src\Clinical\Domain\EncounterProcedureRecord;
use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Clinical\Domain\Odontogram;
use Src\Clinical\Domain\OdontogramRepository;
use Src\Clinical\Domain\Surface;
use Src\Clinical\Domain\Tooth;
use Src\Clinical\Domain\ToothStateEntry;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * ODO-01 sampai ODO-04: odontogram FDI dengan lima permukaan per gigi, kondisi dari master `tooth_conditions`, satu
 * snapshot per kunjungan. Perubahan hanya selama kunjungan berjalan (M6); odontogram terkini dihitung lintas snapshot.
 */
final readonly class OdontogramService
{
    public function __construct(
        private OdontogramRepository $odontogram,
        private EncounterRepository $encounters,
        private EncounterGuard $guard,
        private ToothConditionCatalog $conditions,
        private PatientLookup $patients,
        private AccessLogger $access,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    /**
     * Odontogram terkini pasien. `$encounterId` menandai entri yang dicatat pada kunjungan itu.
     *
     * @return array{teeth: array<int, array{whole: ?array<string, mixed>, surfaces: object}>, conditions: list<array<string, mixed>>}
     */
    public function current(string $patientId, string $route, ?string $encounterId = null): array
    {
        if (! $this->patients->exists($patientId)) {
            throw new EncounterNotFound;
        }
        $this->access->patientRecordOpened($patientId, $route);

        $present = fn (ToothStateEntry $e) => [
            'condition_code' => $e->conditionCode,
            'set_at' => $e->setAt->format('c'),
            'encounter_id' => $e->encounterId,
            'in_this_encounter' => $encounterId !== null && $e->encounterId === $encounterId,
        ];

        $teeth = [];
        foreach (Odontogram::current($this->odontogram->entriesForPatient($patientId)) as $fdi => $state) {
            $teeth[$fdi] = [
                'whole' => $state['whole'] === null ? null : $present($state['whole']),
                // Objek (bukan array) agar JSON tetap `{}` saat gigi hanya punya kondisi seluruh gigi.
                'surfaces' => (object) array_map($present, $state['surfaces']),
            ];
        }

        return ['teeth' => $teeth, 'conditions' => array_map(fn ($c) => $c->toArray(), $this->conditions->all())];
    }

    /**
     * ODO-05: usulan pembaruan odontogram dari tindakan pada kunjungan yang masih berjalan. Tindakan yang kodenya menghasilkan
     * kondisi gigi (diatur Admin di katalog tindakan) dan belum diputuskan dokter. Usulan hanya muncul bila kondisi cocok dengan
     * cakupan tindakan (kondisi per permukaan untuk tindakan per permukaan, kondisi seluruh gigi untuk tindakan per gigi).
     *
     * @return list<array{entry_id: string, procedure_name: string, tooth_fdi: int, surfaces: list<string>, condition_code: string, condition_name: string}>
     */
    public function suggestions(string $encounterId): array
    {
        $list = [];
        foreach ($this->encounters->procedures($encounterId) as $procedure) {
            $targets = $this->targets($procedure);
            $condition = $procedure->resultConditionCode === null ? null : $this->conditions->find($procedure->resultConditionCode);
            if ($targets === null || $procedure->toothFdi === null || $procedure->odontogramStatus !== null || $condition === null || ! $condition->active) {
                continue;
            }
            $whole = $targets === [Surface::Whole->value];
            if (($whole && $condition->scope === 'surface') || (! $whole && $condition->scope === 'tooth')) {
                continue;
            }
            $list[] = [
                'entry_id' => $procedure->id,
                'procedure_name' => $procedure->name,
                'tooth_fdi' => $procedure->toothFdi,
                'surfaces' => $targets,
                'condition_code' => $condition->code,
                'condition_name' => $condition->name,
            ];
        }

        return $list;
    }

    /**
     * Keputusan dokter atas usulan: menerapkan kondisi ke odontogram kunjungan ini, atau mengabaikannya. Keputusan tercatat di
     * audit log dan usulan tidak muncul lagi.
     */
    public function resolveSuggestion(string $actorId, string $encounterId, string $entryId, bool $apply): void
    {
        $this->tx->run(function () use ($actorId, $encounterId, $entryId, $apply): void {
            $encounter = $this->guard->load($encounterId);
            $this->guard->assertOpen($encounter);
            $suggestion = null;
            foreach ($this->suggestions($encounterId) as $candidate) {
                if ($candidate['entry_id'] === $entryId) {
                    $suggestion = $candidate;
                }
            }
            if ($suggestion === null) {
                throw new InvalidClinicalData('odontogram.suggestion_not_found', 'Tidak ada usulan pembaruan odontogram untuk tindakan ini.');
            }

            if ($apply) {
                foreach ($suggestion['surfaces'] as $surface) {
                    $this->setCondition($actorId, $encounterId, $suggestion['tooth_fdi'], $surface, $suggestion['condition_code']);
                }
            }
            $this->encounters->setProcedureOdontogramStatus($actorId, $encounterId, $entryId, $apply ? 'applied' : 'dismissed');
            $this->audit->record(new AuditEntry($actorId, $apply ? 'odontogram.suggestion_applied' : 'odontogram.suggestion_dismissed', 'encounter', $encounterId, null, [
                'entry_id' => $entryId,
                'tooth_fdi' => $suggestion['tooth_fdi'],
                'surfaces' => $suggestion['surfaces'],
                'condition' => $suggestion['condition_code'],
            ]));
        });
    }

    /**
     * Permukaan yang akan diberi kondisi: tiap permukaan untuk tindakan per permukaan, seluruh gigi untuk tindakan per gigi.
     *
     * @return list<string>|null null bila tindakan tidak menyangkut gigi
     */
    private function targets(EncounterProcedureRecord $procedure): ?array
    {
        return match ($procedure->scope) {
            'surface' => $procedure->surfaces === null || $procedure->surfaces === '' ? null : str_split($procedure->surfaces),
            'tooth' => [Surface::Whole->value],
            default => null,
        };
    }

    /**
     * Menetapkan kondisi satu gigi dan satu permukaan (`W` untuk seluruh gigi). Kondisi Sehat mengembalikan keadaan normal.
     */
    public function setCondition(string $actorId, string $encounterId, int $toothFdi, string $surfaceCode, string $conditionCode): void
    {
        $tooth = new Tooth($toothFdi);
        $surface = Surface::tryFrom($surfaceCode) ?? throw new InvalidClinicalData('odontogram.invalid_surface', 'Permukaan gigi tidak valid.');
        $condition = $this->conditions->find($conditionCode);
        if ($condition === null || ! $condition->active) {
            throw new InvalidClinicalData('odontogram.invalid_condition', 'Kondisi gigi tidak ditemukan atau sudah nonaktif.');
        }
        $whole = $surface === Surface::Whole;
        if ($condition->scope === 'tooth' && ! $whole) {
            throw new InvalidClinicalData('odontogram.condition_whole_tooth', "Kondisi {$condition->name} berlaku untuk seluruh gigi, bukan satu permukaan.");
        }
        if ($condition->scope === 'surface' && $whole) {
            throw new InvalidClinicalData('odontogram.condition_surface_only', "Kondisi {$condition->name} dicatat per permukaan.");
        }

        $this->tx->run(function () use ($actorId, $encounterId, $tooth, $surface, $condition): void {
            $encounter = $this->guard->load($encounterId);
            $this->guard->assertOpen($encounter);
            $now = $this->clock->now();

            $snapshot = $this->odontogram->snapshotFor($actorId, $encounter->patientId, $encounterId, $now);
            $previous = $this->odontogram->setState($actorId, $snapshot, $tooth->fdi, $surface, $condition->code, $now);
            $this->odontogram->refreshDentition($snapshot);

            if ($previous !== $condition->code) {
                $this->audit->record(new AuditEntry($actorId, 'odontogram.condition_set', 'encounter', $encounterId, $previous === null ? null : ['condition' => $previous], [
                    'tooth_fdi' => $tooth->fdi,
                    'surface' => $surface->value,
                    'condition' => $condition->code,
                ]));
            }
        });
    }
}
