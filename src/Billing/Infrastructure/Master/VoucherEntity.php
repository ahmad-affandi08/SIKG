<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Master;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Src\Billing\Domain\DiscountKind;
use Src\Billing\Infrastructure\Eloquent\VoucherModel;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * P3-06: kode promo. Nilai berupa persen bulat (1 sampai 100) untuk jenis persen, atau rupiah bulat untuk jenis nominal.
 */
final class VoucherEntity extends EloquentMasterEntity
{
    public function key(): string
    {
        return 'vouchers';
    }

    public function title(): string
    {
        return 'Voucher dan kode promo';
    }

    public function viewPermission(): string
    {
        return 'invoice.view';
    }

    public function managePermission(): string
    {
        return 'tariff.manage';
    }

    public function fields(): array
    {
        return [
            new MasterField('code', 'Kode promo', 'text', required: true, max: 30, help: 'Huruf, angka, garis bawah, dan strip; disimpan huruf besar.'),
            new MasterField('name', 'Nama promo', 'text', required: true, max: 150),
            new MasterField('kind', 'Jenis diskon', 'select', required: true, options: [
                ['value' => DiscountKind::Percent->value, 'label' => 'Persen'],
                ['value' => DiscountKind::Amount->value, 'label' => 'Nominal (Rp)'],
            ]),
            new MasterField('value', 'Nilai', 'number', required: true, help: 'Persen bulat 1 sampai 100 untuk jenis persen; rupiah bulat untuk jenis nominal.'),
            new MasterField('min_subtotal_amount', 'Subtotal minimum (Rp)', 'money', required: true, help: 'Isi 0 bila tanpa minimum.'),
            new MasterField('max_redemptions', 'Batas pemakaian total', 'number', help: 'Kosong berarti tanpa batas.'),
            new MasterField('max_per_patient', 'Batas pemakaian per pasien', 'number', required: true, help: 'Pemakaian pada tagihan yang masih aktif (tidak dibatalkan).'),
            new MasterField('valid_from', 'Berlaku mulai', 'date'),
            new MasterField('valid_to', 'Berlaku sampai', 'date'),
        ];
    }

    public function columns(): array
    {
        return ['code', 'name', 'kind', 'value', 'valid_to'];
    }

    public function rules(?string $ignoreId): array
    {
        $clinicId = app(TenantContext::class)->clinicId();
        $valueRule = new class implements DataAwareRule, ValidationRule
        {
            /** @var array<string, mixed> */
            private array $data = [];

            /** @param  array<string, mixed>  $data */
            public function setData(array $data): static
            {
                $this->data = $data;

                return $this;
            }

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (($this->data['kind'] ?? null) === DiscountKind::Percent->value && (int) $value > 100) {
                    $fail('Diskon persen paling besar 100.');
                }
            }
        };

        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('vouchers', 'code')->where('clinic_id', $clinicId)->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::enum(DiscountKind::class)],
            'value' => ['required', 'integer', 'min:1', 'max:9999999999', $valueRule],
            'min_subtotal_amount' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_per_patient' => ['required', 'integer', 'min:1', 'max:1000'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
        ];
    }

    protected function clean(array $data): array
    {
        $clean = parent::clean($data);
        if (isset($clean['code'])) {
            $clean['code'] = strtoupper((string) $clean['code']);
        }

        return $clean;
    }

    protected function modelClass(): string
    {
        return VoucherModel::class;
    }

    protected function subject(): string
    {
        return 'voucher';
    }
}
