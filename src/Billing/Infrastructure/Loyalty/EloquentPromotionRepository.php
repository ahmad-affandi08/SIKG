<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Loyalty;

use Src\Billing\Domain\Loyalty\PromotionRecord;
use Src\Billing\Domain\Loyalty\PromotionRepository;
use Src\Billing\Infrastructure\Eloquent\InvoicePromotionModel;

final class EloquentPromotionRepository implements PromotionRepository
{
    public function activeForInvoice(string $invoiceId): ?PromotionRecord
    {
        $m = InvoicePromotionModel::query()->where('invoice_id', $invoiceId)->where('status', 'applied')->first();

        return $m === null ? null : $this->record($m);
    }

    public function add(PromotionRecord $promotion, string $actorId): void
    {
        $m = new InvoicePromotionModel;
        $m->id = $promotion->id;
        $m->forceFill([
            'invoice_id' => $promotion->invoiceId, 'patient_id' => $promotion->patientId, 'type' => $promotion->type, 'voucher_id' => $promotion->voucherId,
            'points_used' => $promotion->pointsUsed, 'discount_amount' => $promotion->discountAmount, 'status' => 'applied', 'active_slot' => 1, 'created_by' => $actorId,
        ])->save();
    }

    public function release(string $invoiceId, string $reason): ?PromotionRecord
    {
        $m = InvoicePromotionModel::query()->where('invoice_id', $invoiceId)->where('status', 'applied')->lockForUpdate()->first();
        if ($m === null) {
            return null;
        }
        $record = $this->record($m);
        $m->forceFill(['status' => 'released', 'active_slot' => null, 'released_reason' => mb_substr($reason, 0, 100)])->save();

        return $record;
    }

    private function record(InvoicePromotionModel $m): PromotionRecord
    {
        return new PromotionRecord($m->id, $m->invoice_id, $m->patient_id, $m->type, $m->voucher_id, $m->points_used, $m->discount_amount);
    }
}
