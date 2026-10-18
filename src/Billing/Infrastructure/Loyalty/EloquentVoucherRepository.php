<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Loyalty;

use Src\Billing\Domain\DiscountKind;
use Src\Billing\Domain\Loyalty\PromotionRecord;
use Src\Billing\Domain\Loyalty\Voucher;
use Src\Billing\Domain\Loyalty\VoucherRepository;
use Src\Billing\Infrastructure\Eloquent\InvoicePromotionModel;
use Src\Billing\Infrastructure\Eloquent\VoucherModel;

final class EloquentVoucherRepository implements VoucherRepository
{
    public function lockByCode(string $code): ?Voucher
    {
        $m = VoucherModel::query()->where('code', $code)->lockForUpdate()->first();

        return $m === null ? null : $this->voucher($m);
    }

    public function find(string $id): ?Voucher
    {
        $m = VoucherModel::query()->withTrashed()->find($id);

        return $m === null ? null : $this->voucher($m);
    }

    public function appliedTotal(string $voucherId): int
    {
        return InvoicePromotionModel::query()->where('voucher_id', $voucherId)->where('type', PromotionRecord::VOUCHER)->where('status', 'applied')->count();
    }

    public function appliedByPatient(string $voucherId, string $patientId): int
    {
        return InvoicePromotionModel::query()->where('voucher_id', $voucherId)->where('patient_id', $patientId)->where('status', 'applied')->count();
    }

    private function voucher(VoucherModel $m): Voucher
    {
        return new Voucher(
            $m->id, $m->code, $m->name, DiscountKind::from($m->kind), $m->kind === DiscountKind::Percent->value ? $m->value * 100 : $m->value, $m->min_subtotal_amount, $m->max_redemptions, $m->max_per_patient,
            $m->valid_from?->toDateString(), $m->valid_to?->toDateString(), $m->is_active,
        );
    }
}
