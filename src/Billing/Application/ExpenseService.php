<?php

declare(strict_types=1);

namespace Src\Billing\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Domain\Expense;
use Src\Billing\Domain\ExpenseCategoryDirectory;
use Src\Billing\Domain\ExpenseRepository;
use Src\Billing\Domain\InvalidBillingData;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

/**
 * P2-08: pencatatan biaya operasional. Biaya tidak diubah atau dihapus: yang salah dibatalkan dengan alasan lalu dicatat ulang
 * (M5). Setiap langkah tercatat di audit (M10). Izin dicek di Presentation (M9).
 */
final readonly class ExpenseService
{
    public function __construct(
        private ExpenseRepository $expenses,
        private ExpenseCategoryDirectory $categories,
        private Transaction $tx,
        private AuditLogger $audit,
        private IdGenerator $ids,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidBillingData
     */
    public function record(string $actorId, string $expenseDate, string $categoryId, int $amount, ?string $payee, string $description, ?string $referenceNo): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expenseDate, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $expenseDate) {
            throw new InvalidBillingData('expense.date_invalid', 'Tanggal biaya tidak valid.');
        }
        if ($expenseDate > $this->today()) {
            throw new InvalidBillingData('expense.date_future', 'Tanggal biaya tidak boleh di masa depan.');
        }

        return $this->tx->run(function () use ($actorId, $expenseDate, $categoryId, $amount, $payee, $description, $referenceNo): string {
            if (! $this->categories->isActive($categoryId)) {
                throw new InvalidBillingData('expense.category_invalid', 'Kategori biaya tidak ditemukan atau nonaktif.');
            }
            $expense = Expense::record($this->ids->generate(), $expenseDate, $categoryId, $amount, $payee, $description, $referenceNo);
            $this->expenses->add($expense, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'expense.recorded', 'expense', $expense->id, null, ['date' => $expenseDate, 'category_id' => $categoryId, 'amount' => $amount]));

            return $expense->id;
        });
    }

    public function void(string $actorId, string $expenseId, string $reason): void
    {
        $this->tx->run(function () use ($actorId, $expenseId, $reason): void {
            $expense = $this->expenses->lock($expenseId) ?? throw new InvalidBillingData('expense.not_found', 'Biaya tidak ditemukan.');
            $expense->void($reason);
            $this->expenses->saveVoid($expense, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'expense.voided', 'expense', $expense->id, ['amount' => $expense->amount->amount], ['reason' => $expense->voidReason()]));
        });
    }

    private function today(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
    }
}
