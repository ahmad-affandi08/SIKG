<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure;

use Src\Billing\Domain\InstallmentPlan;
use Src\Billing\Domain\InstallmentRepository;
use Src\Billing\Infrastructure\Eloquent\InstallmentModel;
use Src\Billing\Infrastructure\Eloquent\InstallmentPlanModel;
use Src\Shared\Domain\Money;

final class EloquentInstallmentRepository implements InstallmentRepository
{
    public function forInvoice(string $invoiceId): ?InstallmentPlan
    {
        return $this->forInvoices([$invoiceId])[$invoiceId] ?? null;
    }

    public function forInvoices(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }
        $plans = InstallmentPlanModel::query()->whereIn('invoice_id', $invoiceIds)->get();
        $rows = InstallmentModel::query()->whereIn('plan_id', $plans->modelKeys())->orderBy('sequence')->get()->groupBy('plan_id');

        $result = [];
        foreach ($plans as $plan) {
            $installments = [];
            foreach ($rows->get($plan->id, []) as $row) {
                $installments[] = ['id' => $row->id, 'sequence' => $row->sequence, 'due_date' => $row->due_date->toDateString(), 'amount' => Money::rupiah($row->amount)];
            }
            $result[$plan->invoice_id] = InstallmentPlan::reconstitute($plan->id, $plan->invoice_id, Money::rupiah($plan->base_paid_amount), $installments);
        }

        return $result;
    }

    public function save(InstallmentPlan $plan, string $actorId): void
    {
        $row = new InstallmentPlanModel;
        $row->forceFill([
            'id' => $plan->id,
            'invoice_id' => $plan->invoiceId,
            'installment_count' => count($plan->installments),
            'base_paid_amount' => $plan->basePaid->amount,
            'created_by' => $actorId,
        ])->save();

        foreach ($plan->installments as $installment) {
            $item = new InstallmentModel;
            $item->forceFill([
                'id' => $installment['id'],
                'plan_id' => $plan->id,
                'invoice_id' => $plan->invoiceId,
                'sequence' => $installment['sequence'],
                'due_date' => $installment['due_date'],
                'amount' => $installment['amount']->amount,
            ])->save();
        }
    }
}
