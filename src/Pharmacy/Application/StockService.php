<?php

declare(strict_types=1);

namespace Src\Pharmacy\Application;

use DateTimeImmutable;
use Src\Pharmacy\Contracts\StockLedger;
use Src\Pharmacy\Domain\InsufficientStock;
use Src\Pharmacy\Domain\InvalidPharmacyData;
use Src\Pharmacy\Domain\MovementType;
use Src\Pharmacy\Domain\StockMovementRecord;
use Src\Pharmacy\Domain\StockRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * OBT-05: stok masuk, stok keluar (penjualan dan pemakaian), dan penyesuaian (opname) dengan alasan wajib. Saldo diubah di bawah
 * kunci baris; stok tidak boleh negatif. Override oleh Admin dengan alasan dicatat sebagai penyesuaian sebesar kekurangan.
 */
final readonly class StockService implements StockLedger
{
    public function __construct(
        private StockRepository $stock,
        private MedicineChecker $medicines,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    public function receive(string $actorId, string $medicineId, int $quantity, ?string $batchNo, ?string $expiryDate, ?string $note): int
    {
        $this->positive($quantity);
        $expiry = $expiryDate === null || $expiryDate === '' ? null : $this->date($expiryDate);

        return $this->tx->run(function () use ($actorId, $medicineId, $quantity, $batchNo, $expiry, $note): int {
            $this->medicines->assertExists($medicineId);
            $this->stock->lockedBalance($medicineId);
            $after = $this->stock->apply($actorId, $medicineId, MovementType::In, $quantity, 'manual', null, $this->text($note), $this->text($batchNo), $expiry, $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'stock.received', 'medicine', $medicineId, null, ['quantity' => $quantity, 'balance_after' => $after, 'batch_no' => $this->text($batchNo), 'expiry_date' => $expiry]));

            return $after;
        });
    }

    /**
     * Stok keluar manual: penjualan atau pemakaian internal. Alasan wajib.
     */
    public function dispose(string $actorId, string $medicineId, int $quantity, string $reason, ?string $overrideReason = null): int
    {
        $this->positive($quantity);
        $reason = $this->required($reason, 'Alasan stok keluar wajib diisi.');

        return $this->tx->run(function () use ($actorId, $medicineId, $quantity, $reason, $overrideReason): int {
            $this->medicines->assertExists($medicineId);

            return $this->takeOut($actorId, $medicineId, $quantity, 'manual', null, $reason, $overrideReason);
        });
    }

    /**
     * Penyesuaian (opname): menetapkan jumlah fisik. Alasan wajib; selisih dicatat sebagai satu mutasi bertanda.
     */
    public function adjust(string $actorId, string $medicineId, int $countedQuantity, string $reason): int
    {
        if ($countedQuantity < 0) {
            throw new InvalidPharmacyData('stock.invalid_quantity', 'Jumlah fisik tidak boleh negatif.');
        }
        $reason = $this->required($reason, 'Alasan penyesuaian stok wajib diisi.');

        return $this->tx->run(function () use ($actorId, $medicineId, $countedQuantity, $reason): int {
            $this->medicines->assertExists($medicineId);
            $current = $this->stock->lockedBalance($medicineId);
            $delta = $countedQuantity - $current;
            if ($delta === 0) {
                return $current;
            }

            $after = $this->stock->apply($actorId, $medicineId, MovementType::Adjustment, $delta, 'manual', null, $reason, null, null, $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'stock.adjusted', 'medicine', $medicineId, ['quantity' => $current], ['quantity' => $after, 'reason' => $reason]));

            return $after;
        });
    }

    public function issue(string $actorId, string $medicineId, int $quantity, string $referenceType, string $referenceId, ?string $overrideReason = null): void
    {
        $this->positive($quantity);
        $this->tx->run(function () use ($actorId, $medicineId, $quantity, $referenceType, $referenceId, $overrideReason): void {
            $this->takeOut($actorId, $medicineId, $quantity, $referenceType, $referenceId, null, $overrideReason);
        });
    }

    public function restore(string $actorId, string $medicineId, int $quantity, string $referenceType, string $referenceId, string $reason): void
    {
        $this->positive($quantity);
        $this->tx->run(function () use ($actorId, $medicineId, $quantity, $referenceType, $referenceId, $reason): void {
            $this->stock->lockedBalance($medicineId);
            $after = $this->stock->apply($actorId, $medicineId, MovementType::In, $quantity, $referenceType, $referenceId, $reason, null, null, $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'stock.restored', 'medicine', $medicineId, null, ['quantity' => $quantity, 'balance_after' => $after, 'reference_type' => $referenceType, 'reference_id' => $referenceId]));
        });
    }

    public function available(string $medicineId): int
    {
        return $this->tx->run(fn (): int => $this->stock->lockedBalance($medicineId));
    }

    /**
     * @return array{medicines: list<array<string, mixed>>}
     */
    public function overview(): array
    {
        return ['medicines' => $this->stock->overview()];
    }

    /**
     * @return list<StockMovementRecord>
     */
    public function history(string $medicineId): array
    {
        return $this->stock->movements($medicineId, 50);
    }

    private function takeOut(string $actorId, string $medicineId, int $quantity, string $referenceType, ?string $referenceId, ?string $reason, ?string $overrideReason): int
    {
        $current = $this->stock->lockedBalance($medicineId);
        $now = $this->clock->now();

        if ($current < $quantity) {
            if ($overrideReason === null || trim($overrideReason) === '') {
                throw new InsufficientStock($current, $quantity);
            }
            // Override Admin: kekurangan dicatat sebagai penyesuaian bertanda sebelum stok keluar.
            $shortfall = $quantity - $current;
            $this->stock->apply($actorId, $medicineId, MovementType::Adjustment, $shortfall, $referenceType, $referenceId, 'Override: '.trim($overrideReason), null, null, $now);
            $this->audit->record(new AuditEntry($actorId, 'stock.override', 'medicine', $medicineId, ['quantity' => $current], ['quantity' => $quantity, 'reason' => trim($overrideReason)]));
        }

        $after = $this->stock->apply($actorId, $medicineId, MovementType::Out, -$quantity, $referenceType, $referenceId, $reason, null, null, $now);
        $this->audit->record(new AuditEntry($actorId, 'stock.issued', 'medicine', $medicineId, null, ['quantity' => $quantity, 'balance_after' => $after, 'reference_type' => $referenceType, 'reference_id' => $referenceId]));

        return $after;
    }

    private function positive(int $quantity): void
    {
        if ($quantity < 1 || $quantity > 1_000_000) {
            throw new InvalidPharmacyData('stock.invalid_quantity', 'Jumlah harus bilangan bulat positif.');
        }
    }

    private function required(string $text, string $message): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidPharmacyData('stock.reason_required', $message);
        }

        return mb_substr($text, 0, 255);
    }

    private function text(?string $text): ?string
    {
        return $text === null || trim($text) === '' ? null : mb_substr(trim($text), 0, 255);
    }

    private function date(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidPharmacyData('stock.invalid_expiry', 'Tanggal kedaluwarsa tidak valid.');
        }

        return $date;
    }
}
