<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure;

use Src\Billing\Domain\Expense;
use Src\Billing\Domain\ExpenseRepository;
use Src\Billing\Infrastructure\Eloquent\ExpenseModel;
use Src\Shared\Application\Clock;

final readonly class EloquentExpenseRepository implements ExpenseRepository
{
    public function __construct(private Clock $clock) {}

    public function add(Expense $expense, string $actorId): void
    {
        $m = new ExpenseModel;
        $m->id = $expense->id;
        $m->forceFill([
            'expense_date' => $expense->expenseDate, 'category_id' => $expense->categoryId, 'amount' => $expense->amount->amount, 'payee' => $expense->payee,
            'description' => $expense->description, 'reference_no' => $expense->referenceNo, 'status' => 'recorded', 'created_by' => $actorId,
        ])->save();
    }

    public function lock(string $id): ?Expense
    {
        $m = ExpenseModel::query()->lockForUpdate()->find($id);

        return $m === null ? null : $this->expense($m);
    }

    public function saveVoid(Expense $expense, string $actorId): void
    {
        ExpenseModel::query()->whereKey($expense->id)->update(['status' => 'void', 'void_reason' => $expense->voidReason(), 'voided_at' => $this->clock->now(), 'voided_by' => $actorId, 'updated_by' => $actorId]);
    }

    public function between(string $from, string $to, ?string $categoryId, bool $includeVoided, int $limit): array
    {
        return array_values(ExpenseModel::query()
            ->whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to)
            ->when($categoryId !== null, fn ($q) => $q->where('category_id', $categoryId))
            ->when(! $includeVoided, fn ($q) => $q->where('status', 'recorded'))
            ->orderByDesc('expense_date')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (ExpenseModel $m) => $this->expense($m))->all());
    }

    public function total(string $from, string $to): int
    {
        return (int) ExpenseModel::query()->whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to)->where('status', 'recorded')->sum('amount');
    }

    private function expense(ExpenseModel $m): Expense
    {
        return Expense::restore($m->id, $m->expense_date->toDateString(), $m->category_id, $m->amount, $m->payee, $m->description, $m->reference_no, $m->status === 'void', $m->void_reason);
    }
}
