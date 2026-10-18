<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Master;

use Src\Billing\Infrastructure\Eloquent\VisitPackageModel;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * P3-06: katalog paket kunjungan (sesi prabayar). Harga dan batas nilai per sesi disalin ke paket pasien saat dibeli (M7).
 */
final class VisitPackageEntity extends EloquentMasterEntity
{
    public function key(): string
    {
        return 'visit-packages';
    }

    public function title(): string
    {
        return 'Paket kunjungan';
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
            new MasterField('name', 'Nama paket', 'text', required: true, max: 150),
            new MasterField('sessions', 'Jumlah sesi', 'number', required: true),
            new MasterField('price_amount', 'Harga paket (Rp)', 'money', required: true, help: 'Rupiah bulat, ditagihkan saat paket dibeli.'),
            new MasterField('max_session_value_amount', 'Nilai maksimum per sesi (Rp)', 'money', help: 'Batas nilai item yang ditanggung satu sesi. Kosong berarti seharga item.'),
            new MasterField('valid_days', 'Masa berlaku (hari)', 'number', help: 'Kosong berarti tanpa batas waktu.'),
        ];
    }

    public function columns(): array
    {
        return ['name', 'sessions', 'price_amount', 'valid_days'];
    }

    public function rules(?string $ignoreId): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'sessions' => ['required', 'integer', 'min:1', 'max:200'],
            'price_amount' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'max_session_value_amount' => ['nullable', 'integer', 'min:1', 'max:9999999999'],
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    protected function modelClass(): string
    {
        return VisitPackageModel::class;
    }

    protected function subject(): string
    {
        return 'visit_package';
    }
}
