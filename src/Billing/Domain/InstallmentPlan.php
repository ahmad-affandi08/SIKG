<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Domain\Money;

/**
 * Jadwal cicilan sisa tagihan (KSR-05), misalnya perawatan kawat gigi. Jadwal hanya menetapkan jumlah dan tanggal jatuh
 * tempo; status tiap cicilan DITURUNKAN dari jumlah dibayar setelah jadwal dibuat (pembayaran mengisi cicilan berurutan),
 * bukan disimpan, sehingga selalu konsisten dengan pembayaran dan refund.
 */
final readonly class InstallmentPlan
{
    public const MIN_COUNT = 2;

    public const MAX_COUNT = 36;

    /**
     * @param  list<array{id: string, sequence: int, due_date: string, amount: Money}>  $installments
     */
    private function __construct(
        public string $id,
        public string $invoiceId,
        public Money $basePaid,
        public array $installments,
    ) {}

    /**
     * @param  list<array{id: string, sequence: int, due_date: string, amount: Money}>  $installments
     */
    public static function reconstitute(string $id, string $invoiceId, Money $basePaid, array $installments): self
    {
        return new self($id, $invoiceId, $basePaid, $installments);
    }

    /**
     * Membagi sisa tagihan sama rata; sisa pembulatan rupiah masuk ke cicilan pertama. Jatuh tempo tiap bulan pada tanggal
     * yang sama (dipangkas ke hari terakhir bulan yang lebih pendek).
     *
     * @param  string  $firstDueDate  YYYY-MM-DD
     */
    public static function create(string $invoiceId, Money $balance, Money $basePaid, int $count, string $firstDueDate, IdGenerator $ids): self
    {
        if ($count < self::MIN_COUNT || $count > self::MAX_COUNT) {
            throw new InvalidBillingData('installment.invalid_count', 'Jumlah cicilan harus antara '.self::MIN_COUNT.' dan '.self::MAX_COUNT.'.');
        }
        if ($balance->isZero()) {
            throw new InvalidBillingData('installment.nothing_to_split', 'Tidak ada sisa tagihan untuk dicicil.');
        }
        $first = DateTimeImmutable::createFromFormat('!Y-m-d', $firstDueDate);
        if ($first === false || $first->format('Y-m-d') !== $firstDueDate) {
            throw new InvalidBillingData('installment.invalid_date', 'Tanggal jatuh tempo pertama tidak valid.');
        }

        $each = intdiv($balance->amount, $count);
        $remainder = $balance->amount - $each * $count;
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'id' => $ids->generate(),
                'sequence' => $i + 1,
                'due_date' => self::addMonths($first, $i)->format('Y-m-d'),
                'amount' => Money::rupiah($each + ($i === 0 ? $remainder : 0)),
            ];
        }

        return new self($ids->generate(), $invoiceId, $basePaid, $rows);
    }

    /**
     * Status tiap cicilan terhadap jumlah dibayar saat ini: paid, partial, overdue (lewat jatuh tempo dan belum lunas), upcoming.
     *
     * @param  string  $today  tanggal lokal klinik YYYY-MM-DD
     * @return list<array{id: string, sequence: int, due_date: string, amount: int, covered: int, status: string}>
     */
    public function statuses(Money $paidNow, string $today): array
    {
        $pool = max(0, $paidNow->amount - $this->basePaid->amount);
        $result = [];
        foreach ($this->installments as $row) {
            $amount = $row['amount']->amount;
            $covered = min($pool, $amount);
            $pool -= $covered;
            $status = match (true) {
                $covered >= $amount => 'paid',
                $row['due_date'] < $today => 'overdue',
                $covered > 0 => 'partial',
                default => 'upcoming',
            };
            $result[] = ['id' => $row['id'], 'sequence' => $row['sequence'], 'due_date' => $row['due_date'], 'amount' => $amount, 'covered' => $covered, 'status' => $status];
        }

        return $result;
    }

    public function hasOverdue(Money $paidNow, string $today): bool
    {
        foreach ($this->statuses($paidNow, $today) as $row) {
            if ($row['status'] === 'overdue') {
                return true;
            }
        }

        return false;
    }

    private static function addMonths(DateTimeImmutable $base, int $months): DateTimeImmutable
    {
        $firstOfTarget = $base->modify('first day of this month')->modify("+{$months} months");
        $day = min((int) $base->format('j'), (int) $firstOfTarget->format('t'));

        return $firstOfTarget->setDate((int) $firstOfTarget->format('Y'), (int) $firstOfTarget->format('n'), $day);
    }
}
