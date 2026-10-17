<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Master;

use Illuminate\Validation\Rule;
use Src\Clinic\Domain\PaymentMethodType;
use Src\Clinic\Infrastructure\Eloquent\PaymentMethodModel;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * MST-06: metode pembayaran (tunai, transfer, QRIS, debit, kredit), dicatat manual.
 */
final class PaymentMethodEntity extends EloquentMasterEntity
{
    public function key(): string
    {
        return 'payment-methods';
    }

    public function title(): string
    {
        return 'Metode pembayaran';
    }

    public function viewPermission(): string
    {
        return 'setting.manage';
    }

    public function managePermission(): string
    {
        return 'setting.manage';
    }

    public function fields(): array
    {
        return [
            new MasterField('name', 'Nama metode', 'text', required: true, max: 100),
            new MasterField('type', 'Jenis', 'select', required: true, options: array_map(
                fn (PaymentMethodType $t) => ['value' => $t->value, 'label' => $t->label()],
                PaymentMethodType::cases(),
            )),
        ];
    }

    public function columns(): array
    {
        return ['name', 'type'];
    }

    public function rules(?string $ignoreId): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(PaymentMethodType::class)],
        ];
    }

    protected function modelClass(): string
    {
        return PaymentMethodModel::class;
    }

    protected function subject(): string
    {
        return 'payment_method';
    }
}
