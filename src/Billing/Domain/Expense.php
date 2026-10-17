<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

use Src\Shared\Domain\Money;

/**
 * P2-08: satu biaya operasional klinik (sewa, listrik, gaji, perlengkapan, dan sejenisnya). Jumlah bilangan bulat rupiah (M4).
 * Biaya yang salah tidak diubah atau dihapus (M5): dibatalkan dengan alasan, lalu dicatat ulang dengan benar. Biaya batal tidak
 * masuk laporan laba rugi.
 */
final class Expense
{
    public const MAX_AMOUNT = 999_999_999_999;

    private function __construct(
        public readonly string $id,
        public readonly string $expenseDate,
        public readonly string $categoryId,
        public readonly Money $amount,
        public readonly ?string $payee,
        public readonly string $description,
        public readonly ?string $referenceNo,
        private bool $voided,
        private ?string $voidReason,
    ) {}

    public static function record(string $id, string $expenseDate, string $categoryId, int $amount, ?string $payee, string $description, ?string $referenceNo): self
    {
        if ($amount < 1 || $amount > self::MAX_AMOUNT) {
            throw new InvalidBillingData('expense.amount_invalid', 'Jumlah biaya harus bilangan bulat rupiah lebih dari nol.');
        }
        $description = trim($description);
        if ($description === '') {
            throw new InvalidBillingData('expense.description_required', 'Keterangan biaya wajib diisi.');
        }

        return new self($id, $expenseDate, $categoryId, Money::rupiah($amount), self::clean($payee, 150), mb_substr($description, 0, 255), self::clean($referenceNo, 60), false, null);
    }

    public static function restore(string $id, string $expenseDate, string $categoryId, int $amount, ?string $payee, string $description, ?string $referenceNo, bool $voided, ?string $voidReason): self
    {
        return new self($id, $expenseDate, $categoryId, Money::rupiah($amount), $payee, $description, $referenceNo, $voided, $voidReason);
    }

    public function isVoided(): bool
    {
        return $this->voided;
    }

    public function voidReason(): ?string
    {
        return $this->voidReason;
    }

    public function void(string $reason): void
    {
        if ($this->voided) {
            throw new InvalidBillingData('expense.already_void', 'Biaya ini sudah dibatalkan.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidBillingData('expense.reason_required', 'Alasan pembatalan wajib diisi.');
        }
        $this->voided = true;
        $this->voidReason = mb_substr($reason, 0, 255);
    }

    private static function clean(?string $text, int $max): ?string
    {
        $text = $text === null ? null : trim($text);

        return $text === null || $text === '' ? null : mb_substr($text, 0, $max);
    }
}
