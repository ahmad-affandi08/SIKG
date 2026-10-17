<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Master;

use Src\Pharmacy\Infrastructure\Eloquent\MedicineModel;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * MST-05: katalog obat dan bahan (nama, satuan, harga jual, stok minimum, kelas obat untuk cek alergi).
 */
final class MedicineEntity extends EloquentMasterEntity
{
    public function key(): string
    {
        return 'medicines';
    }

    public function title(): string
    {
        return 'Katalog obat dan bahan';
    }

    public function viewPermission(): string
    {
        return 'stock.view';
    }

    public function managePermission(): string
    {
        return 'stock.manage';
    }

    public function fields(): array
    {
        return [
            new MasterField('name', 'Nama obat atau bahan', 'text', required: true, max: 255),
            new MasterField('unit', 'Satuan', 'text', required: true, max: 30, help: 'Contoh: tablet, kapsul, botol, ampul.'),
            new MasterField('sell_price_amount', 'Harga jual (Rp)', 'money', required: true),
            new MasterField('min_stock', 'Stok minimum', 'number', required: true),
            new MasterField('drug_class', 'Kelas obat', 'text', max: 60, help: 'Untuk peringatan alergi. Huruf kecil, angka, garis bawah; contoh: penisilin, nsaid, anestesi_lokal.'),
        ];
    }

    public function columns(): array
    {
        return ['name', 'unit', 'sell_price_amount', 'min_stock', 'drug_class'];
    }

    public function rules(?string $ignoreId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'sell_price_amount' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'min_stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'drug_class' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
        ];
    }

    protected function modelClass(): string
    {
        return MedicineModel::class;
    }

    protected function subject(): string
    {
        return 'medicine';
    }
}
