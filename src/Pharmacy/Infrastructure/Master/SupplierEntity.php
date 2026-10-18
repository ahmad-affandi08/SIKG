<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Master;

use Src\Pharmacy\Infrastructure\Eloquent\SupplierModel;
use Src\Shared\Application\Master\ImportableMaster;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * P2-05: master pemasok obat dan bahan. Kontak hanya kontak perusahaan pemasok, bukan data pasien.
 */
final class SupplierEntity extends EloquentMasterEntity implements ImportableMaster
{
    public function importKey(): string
    {
        return 'name';
    }

    public function key(): string
    {
        return 'suppliers';
    }

    public function title(): string
    {
        return 'Pemasok obat dan bahan';
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
            new MasterField('name', 'Nama pemasok', 'text', required: true, max: 150),
            new MasterField('contact_name', 'Nama kontak', 'text', max: 100),
            new MasterField('phone', 'Telepon', 'text', max: 30),
            new MasterField('email', 'Email', 'text', max: 150),
            new MasterField('address', 'Alamat', 'text', max: 255),
            new MasterField('notes', 'Catatan', 'textarea', max: 500),
        ];
    }

    public function columns(): array
    {
        return ['name', 'contact_name', 'phone'];
    }

    public function rules(?string $ignoreId): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function modelClass(): string
    {
        return SupplierModel::class;
    }

    protected function subject(): string
    {
        return 'supplier';
    }
}
