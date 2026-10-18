<?php

declare(strict_types=1);

namespace Src\Insurance\Infrastructure\Master;

use Illuminate\Validation\Rule;
use Src\Insurance\Infrastructure\Eloquent\InsurerModel;
use Src\Shared\Application\Master\ImportableMaster;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * P2-02: master penjamin (asuransi dan perusahaan). Kontak hanya kontak lembaga penjamin, bukan data pasien.
 */
final class InsurerEntity extends EloquentMasterEntity implements ImportableMaster
{
    public function importKey(): string
    {
        return 'name';
    }

    public function key(): string
    {
        return 'insurers';
    }

    public function title(): string
    {
        return 'Penjamin (asuransi dan perusahaan)';
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
            new MasterField('name', 'Nama penjamin', 'text', required: true, max: 150),
            new MasterField('type', 'Jenis', 'select', required: true, options: [
                ['value' => 'insurance', 'label' => 'Asuransi'],
                ['value' => 'corporate', 'label' => 'Perusahaan'],
            ]),
            new MasterField('contact_name', 'Nama kontak', 'text', max: 100),
            new MasterField('contact_phone', 'Telepon kontak', 'text', max: 30),
            new MasterField('contact_email', 'Email kontak', 'text', max: 150),
            new MasterField('notes', 'Catatan', 'textarea', max: 500),
        ];
    }

    public function columns(): array
    {
        return ['name', 'type', 'contact_name', 'contact_phone'];
    }

    public function rules(?string $ignoreId): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['insurance', 'corporate'])],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'contact_email' => ['nullable', 'email:rfc', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function modelClass(): string
    {
        return InsurerModel::class;
    }

    protected function subject(): string
    {
        return 'insurer';
    }
}
