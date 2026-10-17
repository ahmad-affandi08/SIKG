<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

interface ExpenseRepository
{
    public function add(Expense $expense, string $actorId): void;

    /**
     * Biaya dikunci selama transaksi pemanggil.
     */
    public function lock(string $id): ?Expense;

    public function saveVoid(Expense $expense, string $actorId): void;

    /**
     * @return list<Expense> terbaru lebih dulu
     */
    public function between(string $from, string $to, ?string $categoryId, bool $includeVoided, int $limit): array;

    /**
     * Total biaya tidak batal dalam rentang.
     */
    public function total(string $from, string $to): int;
}
