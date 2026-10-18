<?php

declare(strict_types=1);

namespace Src\Pharmacy\Application;

use DateTimeZone;
use Src\Clinic\Contracts\BranchDirectory;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Pharmacy\Domain\InvalidPharmacyData;
use Src\Pharmacy\Domain\StockRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

/**
 * P3-04: transfer stok antar cabang. Stok milik satu cabang (atau gudang pusat bila `null`); transfer langsung memindahkan jumlah dan batch
 * (FEFO, nomor batch, kedaluwarsa, dan harga beli ikut) dalam satu transaksi dan tercatat hanya-tambah. Pengguna terikat cabang hanya
 * boleh mengirim dari cabangnya sendiri; pengguna tanpa ikatan cabang dapat memindahkan dari cabang mana pun, termasuk gudang pusat.
 */
final readonly class StockTransferService
{
    public function __construct(
        private StockRepository $stock,
        private MedicineChecker $medicines,
        private BranchDirectory $branches,
        private ClinicCalendar $calendar,
        private TenantContext $tenant,
        private IdGenerator $ids,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    /**
     * @param  string|null  $fromBranchId  null = gudang pusat
     * @param  string|null  $toBranchId  null = gudang pusat
     * @return array{from_after: int, to_after: int}
     */
    public function transfer(string $actorId, string $medicineId, int $quantity, ?string $fromBranchId, ?string $toBranchId, ?string $reason): array
    {
        if ($quantity < 1) {
            throw new InvalidPharmacyData('stock.invalid_quantity', 'Jumlah transfer harus lebih dari nol.');
        }
        if ($fromBranchId === $toBranchId) {
            throw new InvalidPharmacyData('stock.transfer_same_branch', 'Cabang asal dan tujuan harus berbeda.');
        }
        $bound = $this->tenant->branchId();
        if ($bound !== null && $fromBranchId !== $bound) {
            throw new InvalidPharmacyData('stock.transfer_source', 'Anda hanya dapat mengirim stok dari cabang Anda sendiri.');
        }
        $this->assertBranch($fromBranchId);
        $this->assertBranch($toBranchId);
        $reason = $reason === null || trim($reason) === '' ? null : mb_substr(trim($reason), 0, 255);

        return $this->tx->run(function () use ($actorId, $medicineId, $quantity, $fromBranchId, $toBranchId, $reason): array {
            $this->medicines->assertExists($medicineId);
            $transferId = $this->ids->generate();
            $result = $this->stock->transfer($actorId, $transferId, $medicineId, $quantity, $fromBranchId, $toBranchId, $reason, $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'stock.transferred', 'medicine', $medicineId, null, [
                'transfer_id' => $transferId, 'quantity' => $quantity, 'from_branch_id' => $fromBranchId, 'to_branch_id' => $toBranchId, 'from_after' => $result['from_after'], 'to_after' => $result['to_after'],
            ]));

            return $result;
        });
    }

    /**
     * Data halaman transfer: cabang, saldo per cabang, dan riwayat.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $branches = array_values(array_filter($this->branches->all(), fn ($b) => $b->active));
        $names = ['' => 'Gudang pusat'];
        foreach ($this->branches->all() as $b) {
            $names[$b->id] = $b->name;
        }
        $zone = new DateTimeZone($this->calendar->timezone());

        return [
            'branches' => array_map(fn ($b) => ['value' => $b->id, 'label' => $b->name], $branches),
            'bound_branch_id' => $this->tenant->branchId(),
            'balances' => array_map(fn (array $r) => [...$r, 'branch' => $names[(string) $r['branch_id']] ?? '-'], $this->stock->branchBalances()),
            'medicines' => array_values(array_map(fn (array $m) => ['value' => $m['id'], 'label' => $m['name'].' ('.$m['unit'].')'], array_filter($this->stock->overview(), fn (array $m) => $m['is_active']))),
            'transfers' => array_map(fn (array $t) => [
                'id' => $t['id'], 'name' => $t['name'], 'unit' => $t['unit'], 'quantity' => $t['quantity'], 'reason' => $t['reason'],
                'from' => $names[(string) $t['from_branch_id']] ?? '-', 'to' => $names[(string) $t['to_branch_id']] ?? '-',
                'at' => $t['created_at']->setTimezone($zone)->format('Y-m-d H:i'),
            ], $this->stock->transfers(100)),
        ];
    }

    private function assertBranch(?string $branchId): void
    {
        if ($branchId === null) {
            return;
        }
        foreach ($this->branches->all() as $branch) {
            if ($branch->id === $branchId && $branch->active) {
                return;
            }
        }

        throw new InvalidPharmacyData('stock.branch_not_found', 'Cabang tidak ditemukan atau tidak aktif.');
    }
}
