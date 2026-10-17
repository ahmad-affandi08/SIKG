<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use Src\Clinic\Contracts\ToothConditionCatalog;
use Src\Clinical\Domain\EncounterNotFound;
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
