<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use DateTimeImmutable;
use Src\Clinic\Contracts\ProcedureCatalog;
use Src\Clinic\Contracts\ProcedureSnapshot;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Clinical\Domain\PlanItemRecord;
use Src\Clinical\Domain\PlanItemStatus;
use Src\Clinical\Domain\PlanNotFound;
use Src\Clinical\Domain\PlanStatus;
use Src\Clinical\Domain\Tooth;
use Src\Clinical\Domain\TreatmentPlanRecord;
use Src\Clinical\Domain\TreatmentPlanRepository;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\Money;

/**
 * RM-06: rencana perawatan multi-kunjungan dengan estimasi biaya dan status tiap langkah (Direncanakan, Berjalan, Selesai).
 * Tarif disalin ke langkah saat dibuat (MST-08). Status rencana diturunkan dari status langkah (M7). Langkah yang dikerjakan
 * lewat kunjungan tertaut ke kunjungan itu. Rencana hanya dapat diubah selama belum dibatalkan atau selesai.
 */
final readonly class TreatmentPlans
{
    public const MAX_QUANTITY = 99;

    public function __construct(
        private TreatmentPlanRepository $plans,
        private ProcedureCatalog $procedures,
        private PatientLookup $patients,
        private Transaction $tx,
        private AuditLogger $audit,
        private AccessLogger $access,
        private Clock $clock,
    ) {}

    /**
     * @return array{patient: array{id: string, name: string, medical_record_no: string}, plans: list<TreatmentPlanRecord>}
     */
    public function overview(string $patientId, string $route): array
    {
        $patient = $this->patients->summaries([$patientId])[$patientId] ?? throw new PlanNotFound('Pasien tidak ditemukan.');
        $this->access->patientRecordOpened($patientId, $route);

        return [
            'patient' => ['id' => $patient->id, 'name' => $patient->name, 'medical_record_no' => $patient->medicalRecordNo],
            'plans' => $this->plans->forPatient($patientId),
        ];
    }

    /**
     * Satu rencana beserta pasiennya, untuk formulir langkah baru.
     *
     * @return array{patient: array{id: string, name: string, medical_record_no: string}, plan: TreatmentPlanRecord}
     */
    public function detail(string $planId): array
    {
        $plan = $this->plans->find($planId) ?? throw new PlanNotFound;
        $patient = $this->patients->summaries([$plan->patientId])[$plan->patientId] ?? throw new PlanNotFound('Pasien tidak ditemukan.');

        return ['patient' => ['id' => $patient->id, 'name' => $patient->name, 'medical_record_no' => $patient->medicalRecordNo], 'plan' => $plan];
    }

    /**
     * Langkah yang masih terbuka untuk pasien, untuk dikerjakan dari layar pemeriksaan.
     *
     * @return list<PlanItemRecord>
     */
    public function openItems(string $patientId): array
    {
        return $this->plans->openItemsForPatient($patientId);
    }

    public function create(string $actorId, string $patientId, string $title, ?string $notes): string
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidClinicalData('plan.title_required', 'Judul rencana wajib diisi.');
        }
        if (! $this->patients->exists($patientId)) {
            throw new PlanNotFound('Pasien tidak ditemukan.');
        }

        return $this->tx->run(function () use ($actorId, $patientId, $title, $notes): string {
            $id = $this->plans->create($actorId, $patientId, mb_substr($title, 0, 150), $this->clean($notes), $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'treatment_plan.created', 'treatment_plan', $id, null, ['patient_id' => $patientId, 'title' => $title]));

            return $id;
        });
    }

    public function addItem(string $actorId, string $planId, string $procedureId, ?int $toothFdi, int $quantity, ?string $notes): string
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidClinicalData('clinical.invalid_quantity', 'Jumlah tindakan harus antara 1 dan '.self::MAX_QUANTITY.'.');
        }

        return $this->tx->run(function () use ($actorId, $planId, $procedureId, $toothFdi, $quantity, $notes): string {
            $plan = $this->editable($planId);
            $procedure = $this->procedures->find($procedureId)
                ?? throw new InvalidClinicalData('clinical.procedure_not_found', 'Tindakan tidak ditemukan atau sudah nonaktif.');
            $tooth = $toothFdi === null ? null : (new Tooth($toothFdi))->fdi;
            if (in_array($procedure->scope, ['tooth', 'surface'], true) && $tooth === null) {
                throw new InvalidClinicalData('plan.tooth_required', 'Tindakan ini dikerjakan per gigi; isi nomor gigi (FDI).');
            }
            $estimate = Money::rupiah($procedure->priceAmount)->multiply($quantity)->amount;

            $id = $this->plans->addItem($actorId, $plan->id, $procedure->id, $procedure->code, $procedure->name, $tooth, $quantity, $procedure->priceAmount, $estimate, $this->clean($notes));
            $this->refreshStatus($actorId, $plan->id);
            $this->audit->record(new AuditEntry($actorId, 'treatment_plan.item_added', 'treatment_plan', $plan->id, null, [
                'item_id' => $id, 'code' => $procedure->code, 'tooth_fdi' => $tooth, 'quantity' => $quantity, 'estimate_amount' => $estimate,
            ]));

            return $id;
        });
    }

    public function removeItem(string $actorId, string $planId, string $itemId): void
    {
        $this->tx->run(function () use ($actorId, $planId, $itemId): void {
            $plan = $this->editable($planId);
            $item = $this->itemOf($plan, $itemId);
            if ($item->status !== PlanItemStatus::Planned) {
                throw new InvalidClinicalData('plan.item_not_removable', 'Hanya langkah yang masih berstatus Direncanakan yang dapat dihapus.');
            }
            $this->plans->removeItem($actorId, $itemId);
            $this->refreshStatus($actorId, $plan->id);
            $this->audit->record(new AuditEntry($actorId, 'treatment_plan.item_removed', 'treatment_plan', $plan->id, ['item_id' => $itemId, 'code' => $item->procedureCode], null));
        });
    }

    /**
     * Mengubah status langkah secara manual. Penautan ke kunjungan terjadi saat langkah dikerjakan lewat layar pemeriksaan.
     */
    public function changeItemStatus(string $actorId, string $planId, string $itemId, PlanItemStatus $status, ?string $encounterId = null): void
    {
        $this->tx->run(function () use ($actorId, $planId, $itemId, $status, $encounterId): void {
            $plan = $this->editable($planId);
            $item = $this->itemOf($plan, $itemId);
            if ($item->status === $status) {
                return;
            }
            $this->apply($actorId, $plan, $item, $status, $encounterId, $this->clock->now());
        });
    }

    /**
     * Dipanggil dari pencatatan tindakan pada kunjungan: langkah rencana ditandai selesai dan tertaut ke kunjungan itu. Langkah
     * harus milik pasien yang sama dengan kunjungan.
     */
    public function markPerformed(string $actorId, string $patientId, string $itemId, string $encounterId): void
    {
        $item = $this->plans->findItem($itemId) ?? throw new PlanNotFound('Langkah rencana tidak ditemukan.');
        $plan = $this->plans->find($item->planId) ?? throw new PlanNotFound;
        if ($plan->patientId !== $patientId) {
            throw new InvalidClinicalData('plan.item_other_patient', 'Langkah rencana itu bukan milik pasien kunjungan ini.');
        }
        if ($item->status === PlanItemStatus::Done || ! $plan->status->isOpen()) {
            throw new InvalidClinicalData('plan.item_closed', 'Langkah rencana itu sudah selesai atau rencananya sudah ditutup.');
        }
        $this->apply($actorId, $plan, $item, PlanItemStatus::Done, $encounterId, $this->clock->now());
    }

    /**
     * Tindakan yang berasal dari langkah rencana dihapus dari kunjungan: langkah kembali Direncanakan dan penautan dilepas.
     */
    public function revertPerformed(string $actorId, string $itemId): void
    {
        $item = $this->plans->findItem($itemId);
        if ($item === null || $item->status === PlanItemStatus::Planned) {
            return;
        }
        $plan = $this->plans->find($item->planId) ?? throw new PlanNotFound;
        $this->apply($actorId, $plan, $item, PlanItemStatus::Planned, null, $this->clock->now());
    }

    /**
     * Langkah rencana yang akan dikerjakan, dengan data tindakan terkini dari katalog untuk mengisi formulir tindakan. Hanya
     * langkah terbuka milik pasien kunjungan itu.
     *
     * @return array{item: PlanItemRecord, plan_title: string, procedure: ProcedureSnapshot}
     */
    public function itemFor(string $patientId, string $itemId): array
    {
        $item = $this->plans->findItem($itemId) ?? throw new PlanNotFound('Langkah rencana tidak ditemukan.');
        $plan = $this->plans->find($item->planId) ?? throw new PlanNotFound;
        if ($plan->patientId !== $patientId || $item->status === PlanItemStatus::Done || ! $plan->status->isOpen()) {
            throw new PlanNotFound('Langkah rencana tidak tersedia untuk kunjungan ini.');
        }

        $procedure = $this->procedures->find($item->procedureId)
            ?? throw new InvalidClinicalData('clinical.procedure_not_found', 'Tindakan pada langkah ini sudah nonaktif di katalog.');

        return ['item' => $item, 'plan_title' => $plan->title, 'procedure' => $procedure];
    }

    public function cancel(string $actorId, string $planId): void
    {
        $this->tx->run(function () use ($actorId, $planId): void {
            $plan = $this->plans->find($planId) ?? throw new PlanNotFound;
            if ($plan->status === PlanStatus::Cancelled) {
                return;
            }
            if ($plan->status === PlanStatus::Done) {
                throw new InvalidClinicalData('plan.already_done', 'Rencana yang sudah selesai tidak dapat dibatalkan.');
            }
            $this->plans->setStatus($actorId, $planId, PlanStatus::Cancelled);
            $this->audit->record(new AuditEntry($actorId, 'treatment_plan.cancelled', 'treatment_plan', $planId, ['status' => $plan->status->value], ['status' => PlanStatus::Cancelled->value]));
        });
    }

    private function apply(string $actorId, TreatmentPlanRecord $plan, PlanItemRecord $item, PlanItemStatus $status, ?string $encounterId, DateTimeImmutable $at): void
    {
        // Penautan ke kunjungan dipertahankan selama langkah belum dikembalikan ke Direncanakan.
        $link = $status === PlanItemStatus::Planned ? null : ($encounterId ?? $item->encounterId);
        $this->plans->setItemStatus($actorId, $item->id, $status, $link, $status === PlanItemStatus::Done ? $at : null);
        $this->refreshStatus($actorId, $plan->id);
        $this->audit->record(new AuditEntry($actorId, 'treatment_plan.item_status', 'treatment_plan', $plan->id, ['item_id' => $item->id, 'status' => $item->status->value], [
            'status' => $status->value, 'encounter_id' => $encounterId,
        ]));
    }

    /** Status rencana diturunkan lagi dari langkah-langkahnya setelah setiap perubahan. */
    private function refreshStatus(string $actorId, string $planId): void
    {
        $plan = $this->plans->find($planId) ?? throw new PlanNotFound;
        if ($plan->status === PlanStatus::Cancelled) {
            return;
        }
        $derived = PlanStatus::fromItems(array_map(fn (PlanItemRecord $i) => $i->status, $plan->items));
        if ($derived !== $plan->status) {
            $this->plans->setStatus($actorId, $planId, $derived);
        }
    }

    private function editable(string $planId): TreatmentPlanRecord
    {
        $plan = $this->plans->find($planId) ?? throw new PlanNotFound;
        if ($plan->status === PlanStatus::Cancelled) {
            throw new InvalidClinicalData('plan.cancelled', 'Rencana ini sudah dibatalkan dan tidak dapat diubah.');
        }

        return $plan;
    }

    private function itemOf(TreatmentPlanRecord $plan, string $itemId): PlanItemRecord
    {
        foreach ($plan->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        throw new PlanNotFound('Langkah rencana tidak ditemukan.');
    }

    private function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
