<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use Src\Clinic\Contracts\ToothConditionCatalog;
use Src\Clinical\Domain\EncounterNotFound;
use Src\Clinical\Domain\EncounterRecord;
use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\Odontogram;
use Src\Clinical\Domain\OdontogramRepository;
use Src\Clinical\Domain\Surface;
use Src\Clinical\Domain\ToothStateEntry;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AccessLogger;

/**
 * ODO-07: membandingkan odontogram pasien antara dua kunjungan berdampingan. Keadaan "pada kunjungan X" adalah odontogram
 * terkini sampai kunjungan itu, yaitu seluruh entri dari kunjungan itu dan semua kunjungan sebelumnya (M8 membuat satu snapshot
 * per kunjungan, sehingga tiap snapshot hanya memuat perubahan). Perbedaan dihitung per gigi dan per permukaan; kondisi seluruh
 * gigi dibandingkan sebagai satu baris. Hanya baca; pembukaan tercatat di access log (M10).
 */
final readonly class OdontogramComparison
{
    private const SURFACES = ['W', 'B', 'M', 'O', 'D', 'L'];

    public function __construct(
        private OdontogramRepository $odontogram,
        private EncounterRepository $encounters,
        private ToothConditionCatalog $conditions,
        private PatientLookup $patients,
        private AccessLogger $access,
    ) {}

    /**
     * @return array{
     *     patient: array{id: string, name: string, medical_record_no: string},
     *     encounters: list<array{id: string, started_at: string, status: string}>,
     *     left_id: string|null, right_id: string|null,
     *     left: array{teeth: array<int, mixed>, conditions: list<array<string, mixed>>}|null,
     *     right: array{teeth: array<int, mixed>, conditions: list<array<string, mixed>>}|null,
     *     changes: list<array{fdi: int, surface: string, from: string|null, to: string|null}>
     * }
     */
    public function __invoke(string $patientId, ?string $leftId, ?string $rightId, string $route): array
    {
        $patient = $this->patients->summaries([$patientId])[$patientId] ?? throw new EncounterNotFound;
        $this->access->patientRecordOpened($patientId, $route);

        $encounters = $this->encounters->forPatient($patientId);
        usort($encounters, fn (EncounterRecord $a, EncounterRecord $b) => [$a->startedAt, $a->id] <=> [$b->startedAt, $b->id]);
        $options = array_map(fn (EncounterRecord $e) => ['id' => $e->id, 'started_at' => $e->startedAt->format('c'), 'status' => $e->status->value], $encounters);
        $who = ['id' => $patient->id, 'name' => $patient->name, 'medical_record_no' => $patient->medicalRecordNo];
        if ($encounters === []) {
            if ($leftId !== null || $rightId !== null) {
                throw new EncounterNotFound;
            }

            return ['patient' => $who, 'encounters' => [], 'left_id' => null, 'right_id' => null, 'left' => null, 'right' => null, 'changes' => []];
        }

        $byId = [];
        foreach ($encounters as $encounter) {
            $byId[$encounter->id] = $encounter;
        }
        $rightId ??= $encounters[array_key_last($encounters)]->id;
        $leftId ??= count($encounters) > 1 ? $encounters[count($encounters) - 2]->id : $rightId;
        if (! isset($byId[$leftId], $byId[$rightId])) {
            throw new EncounterNotFound;
        }

        $entries = $this->odontogram->entriesForPatient($patientId);
        $left = $this->stateAsOf($byId[$leftId], $encounters, $entries);
        $right = $this->stateAsOf($byId[$rightId], $encounters, $entries);
        $conditions = array_map(fn ($c) => $c->toArray(), $this->conditions->all());

        return [
            'patient' => $who,
            'encounters' => $options,
            'left_id' => $leftId,
            'right_id' => $rightId,
            'left' => ['teeth' => $this->present($left), 'conditions' => $conditions],
            'right' => ['teeth' => $this->present($right), 'conditions' => $conditions],
            'changes' => $this->changes($left, $right),
        ];
    }

    /**
     * @param  list<EncounterRecord>  $encounters  urut waktu mulai
     * @param  list<ToothStateEntry>  $entries
     * @return array<int, array{whole: ?ToothStateEntry, surfaces: array<string, ToothStateEntry>}>
     */
    private function stateAsOf(EncounterRecord $target, array $encounters, array $entries): array
    {
        $included = [];
        foreach ($encounters as $encounter) {
            $included[$encounter->id] = true;
            if ($encounter->id === $target->id) {
                break;
            }
        }

        return Odontogram::current(array_values(array_filter($entries, fn (ToothStateEntry $e) => isset($included[$e->encounterId]))));
    }

    /**
     * Bentuk yang sama dengan odontogram terkini (`OdontogramService::current`) agar bagan di layar dapat dipakai ulang.
     *
     * @param  array<int, array{whole: ?ToothStateEntry, surfaces: array<string, ToothStateEntry>}>  $state
     * @return array<int, mixed>
     */
    private function present(array $state): array
    {
        $entry = fn (ToothStateEntry $e) => [
            'condition_code' => $e->conditionCode,
            'set_at' => $e->setAt->format('c'),
            'encounter_id' => $e->encounterId,
            'in_this_encounter' => false,
        ];

        $teeth = [];
        foreach ($state as $fdi => $tooth) {
            $teeth[$fdi] = [
                'whole' => $tooth['whole'] === null ? null : $entry($tooth['whole']),
                // Objek (bukan array) agar JSON tetap `{}` saat gigi hanya punya kondisi seluruh gigi.
                'surfaces' => (object) array_map($entry, $tooth['surfaces']),
            ];
        }

        return $teeth;
    }

    /**
     * @param  array<int, array{whole: ?ToothStateEntry, surfaces: array<string, ToothStateEntry>}>  $left
     * @param  array<int, array{whole: ?ToothStateEntry, surfaces: array<string, ToothStateEntry>}>  $right
     * @return list<array{fdi: int, surface: string, from: string|null, to: string|null}>
     */
    private function changes(array $left, array $right): array
    {
        $code = function (array $state, int $fdi, string $surface): ?string {
            $tooth = $state[$fdi] ?? null;
            $entry = $surface === Surface::Whole->value ? ($tooth['whole'] ?? null) : ($tooth['surfaces'][$surface] ?? null);

            return $entry?->conditionCode;
        };

        $fdis = array_unique([...array_keys($left), ...array_keys($right)]);
        sort($fdis);
        $changes = [];
        foreach ($fdis as $fdi) {
            foreach (self::SURFACES as $surface) {
                $from = $code($left, $fdi, $surface);
                $to = $code($right, $fdi, $surface);
                if ($from !== $to) {
                    $changes[] = ['fdi' => $fdi, 'surface' => $surface, 'from' => $from, 'to' => $to];
                }
            }
        }

        return $changes;
    }
}
