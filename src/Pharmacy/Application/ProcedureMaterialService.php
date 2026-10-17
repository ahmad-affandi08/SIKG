<?php

declare(strict_types=1);

namespace Src\Pharmacy\Application;

use Src\Clinic\Contracts\ProcedureCatalog;
use Src\Clinical\Domain\Events\ProcedurePerformed;
use Src\Clinical\Domain\Events\ProcedureRetracted;
use Src\Pharmacy\Contracts\MedicineCatalog;
use Src\Pharmacy\Domain\InvalidPharmacyData;
use Src\Pharmacy\Domain\ProcedureMaterialRepository;
use Src\Pharmacy\Domain\StockRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

/**
 * P2-05: bahan habis pakai standar per tindakan. Pengaturan oleh pengelola stok; pemakaian otomatis saat tindakan dicatat pada
 * kunjungan (event ProcedurePerformed) dan dikembalikan bila tindakan dibatalkan sebelum kunjungan ditutup. Pengambilan stok tidak
 * pernah menolak tindakan klinis; kekurangan stok hanya tercatat di audit.
 */
final readonly class ProcedureMaterialService
{
    private const REFERENCE = 'encounter_procedure';

    public function __construct(
        private ProcedureMaterialRepository $materials,
        private ProcedureCatalog $procedures,
        private MedicineCatalog $medicines,
        private StockService $stock,
        private StockRepository $ledger,
        private Transaction $tx,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{materials: list<array{id: string, procedure_id: string, procedure_label: string, medicine_id: string, medicine_name: string, unit: string, quantity: int}>, medicines: list<array{value: string, label: string}>}
     */
    public function overview(): array
    {
        $rows = [];
        foreach ($this->materials->all() as $material) {
            if (! $material->active) {
                continue;
            }
            $procedure = $this->procedures->find($material->procedureId);
            $medicine = $this->medicines->find($material->medicineId);
            $rows[] = [
                'id' => $material->id,
                'procedure_id' => $material->procedureId,
                'procedure_label' => $procedure === null ? '(tindakan nonaktif)' : $procedure->code.' - '.$procedure->name,
                'medicine_id' => $material->medicineId,
                'medicine_name' => $medicine->name ?? '-',
                'unit' => $medicine->unit ?? '',
                'quantity' => $material->quantity,
            ];
        }
        usort($rows, fn (array $a, array $b) => [$a['procedure_label'], $a['medicine_name']] <=> [$b['procedure_label'], $b['medicine_name']]);

        $options = [];
        foreach ($this->ledger->overview() as $m) {
            if ($m['is_active']) {
                $options[] = ['value' => $m['id'], 'label' => $m['name'].' ('.$m['unit'].')'];
            }
        }

        return ['materials' => $rows, 'medicines' => $options];
    }

    public function set(string $actorId, string $procedureId, string $medicineId, int $quantity): void
    {
        if ($quantity < 1 || $quantity > 1000) {
            throw new InvalidPharmacyData('material.invalid_quantity', 'Jumlah bahan per tindakan antara 1 dan 1000.');
        }
        $this->tx->run(function () use ($actorId, $procedureId, $medicineId, $quantity): void {
            if ($this->procedures->find($procedureId) === null) {
                throw new InvalidPharmacyData('material.procedure_not_found', 'Tindakan tidak ditemukan atau nonaktif.');
            }
            $medicine = $this->medicines->find($medicineId);
            if ($medicine === null || ! $medicine->active) {
                throw new InvalidPharmacyData('stock.medicine_not_found', 'Obat tidak ditemukan atau sudah nonaktif.');
            }
            $material = $this->materials->set($actorId, $procedureId, $medicineId, $quantity);
            $this->audit->record(new AuditEntry($actorId, 'material.set', 'procedure', $procedureId, null, ['medicine_id' => $medicineId, 'quantity' => $quantity, 'material_id' => $material->id]));
        });
    }

    public function remove(string $actorId, string $materialId): void
    {
        $this->tx->run(function () use ($actorId, $materialId): void {
            $material = $this->materials->find($materialId) ?? throw new InvalidPharmacyData('material.not_found', 'Bahan tidak ditemukan.');
            $this->materials->deactivate($actorId, $materialId);
            $this->audit->record(new AuditEntry($actorId, 'material.removed', 'procedure', $material->procedureId, ['medicine_id' => $material->medicineId, 'quantity' => $material->quantity], null));
        });
    }

    /**
     * Pendengar ProcedurePerformed.
     */
    public function procedurePerformed(ProcedurePerformed $event): void
    {
        if ($event->procedureId === null || $event->performedBy === null) {
            return;
        }
        foreach ($this->materials->activeFor($event->procedureId) as $material) {
            $this->stock->consumeMaterial($event->performedBy, $material->medicineId, $material->quantity * max(1, $event->quantity), self::REFERENCE, $event->encounterProcedureId);
        }
    }

    /**
     * Pendengar ProcedureRetracted: mengembalikan bahan yang tercatat keluar untuk tindakan itu.
     */
    public function procedureRetracted(ProcedureRetracted $event): void
    {
        if ($event->performedBy === null) {
            return;
        }
        foreach ($this->ledger->referencedMedicines(self::REFERENCE, $event->encounterProcedureId) as $medicineId) {
            $this->stock->returnMaterial($event->performedBy, $medicineId, self::REFERENCE, $event->encounterProcedureId);
        }
    }
}
