<?php

declare(strict_types=1);

namespace Src\Billing\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Domain\Expense;
use Src\Billing\Domain\ExpenseCategoryDirectory;
use Src\Billing\Domain\ExpenseRepository;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Shared\Application\Clock;

/**
 * P2-08: daftar biaya operasional dalam rentang tanggal dan data formulir. Hanya baca; izin dicek di Presentation.
 */
final readonly class ExpenseQueries
{
    private const LIMIT = 300;

    public function __construct(
        private ExpenseRepository $expenses,
        private ExpenseCategoryDirectory $categories,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @return array{expenses: list<array<string, mixed>>, total_amount: int, categories: list<array{value: string, label: string}>, today: string, range: array{from: string, to: string}}
     */
    public function list(?string $from, ?string $to, ?string $categoryId): array
    {
        $today = $this->today();
        $from = $this->validDate($from) ?? substr($today, 0, 8).'01';
        $to = $this->validDate($to) ?? $today;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $records = $this->expenses->between($from, $to, $categoryId, true, self::LIMIT);
        $names = $this->categories->names(array_values(array_unique(array_map(fn (Expense $e) => $e->categoryId, $records))));

        return [
            'expenses' => array_map(fn (Expense $e): array => [
                'id' => $e->id,
                'expense_date' => $e->expenseDate,
                'category_name' => $names[$e->categoryId] ?? '-',
                'amount' => $e->amount->amount,
                'payee' => $e->payee,
                'description' => $e->description,
                'reference_no' => $e->referenceNo,
                'voided' => $e->isVoided(),
                'void_reason' => $e->voidReason(),
            ], $records),
            'total_amount' => $this->expenses->total($from, $to),
            'categories' => $this->categories->options(),
            'today' => $today,
            'range' => ['from' => $from, 'to' => $to],
        ];
    }

    /**
     * @return array{categories: list<array{value: string, label: string}>, today: string}
     */
    public function formData(): array
    {
        return ['categories' => $this->categories->options(), 'today' => $this->today()];
    }

    private function validDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        return $parsed !== false && $parsed->format('Y-m-d') === $date ? $date : null;
    }

    private function today(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
    }
}
