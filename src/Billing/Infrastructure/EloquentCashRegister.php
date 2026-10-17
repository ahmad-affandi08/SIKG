<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Src\Billing\Application\CashRegister;
use Src\Billing\Infrastructure\Eloquent\CashClosingModel;
use Src\Billing\Infrastructure\Eloquent\PaymentModel;
use Src\Clinic\Contracts\PaymentMethodCatalog;
use Src\IdentityAccess\Contracts\UserLookup;

final readonly class EloquentCashRegister implements CashRegister
{
    public function __construct(private PaymentMethodCatalog $methods, private UserLookup $users) {}

    public function unclosed(string $cashierId, DateTimeImmutable $before): array
    {
        $amount = 0;
        $count = 0;
        foreach ($this->scope($cashierId, $before)->get(['type', 'amount']) as $payment) {
            $amount += $payment->type === 'refund' ? -$payment->amount : $payment->amount;
            $count++;
        }

        return ['amount' => $amount, 'payments' => $count];
    }

    public function close(string $actorId, string $cashierId, string $date, int $systemAmount, int $countedAmount, int $differenceAmount, ?string $note, DateTimeImmutable $at, DateTimeImmutable $before): string
    {
        $closing = new CashClosingModel;
        $closing->forceFill([
            'closing_date' => $date,
            'cashier_id' => $cashierId,
            'system_amount' => $systemAmount,
            'counted_amount' => $countedAmount,
            'difference_amount' => $differenceAmount,
            'note' => $note,
            'closed_at' => $at,
            'created_by' => $actorId,
        ])->save();

        $this->scope($cashierId, $before)->lockForUpdate()->get()->each(function (PaymentModel $payment) use ($closing): void {
            $payment->forceFill(['cash_closing_id' => $closing->id])->save();
        });

        return $closing->id;
    }

    public function history(?string $cashierId, int $limit): array
    {
        $rows = CashClosingModel::query()
            ->when($cashierId !== null, fn (Builder $q) => $q->where('cashier_id', $cashierId))
            ->orderByDesc('closed_at')->orderByDesc('id')->limit($limit)->get();
        $ids = [];
        foreach ($rows as $row) {
            $ids[$row->cashier_id] = $row->cashier_id;
        }
        $names = $this->users->names(array_values($ids));

        return array_values($rows->map(fn (CashClosingModel $c) => [
            'id' => $c->id,
            'closing_date' => $c->closing_date->toDateString(),
            'cashier_name' => $names[$c->cashier_id] ?? null,
            'system_amount' => $c->system_amount,
            'counted_amount' => $c->counted_amount,
            'difference_amount' => $c->difference_amount,
            'note' => $c->note,
            'closed_at' => $c->closed_at->format('c'),
        ])->all());
    }

    /**
     * @return Builder<PaymentModel>
     */
    private function scope(string $cashierId, DateTimeImmutable $before): Builder
    {
        return PaymentModel::query()
            ->where('received_by', $cashierId)
            ->whereNull('cash_closing_id')
            ->whereIn('payment_method_id', $this->methods->cashMethodIds())
            ->where('paid_at', '<', $before);
    }
}
