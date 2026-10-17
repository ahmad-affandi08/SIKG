<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Master;

use Illuminate\Validation\Rule;
use Src\Clinic\Infrastructure\Eloquent\ToothConditionModel;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * ODO-03: kondisi gigi yang dapat diatur Admin (kode, nama, warna, dan apakah berlaku per permukaan atau seluruh gigi).
 * Kode tidak berubah setelah dibuat karena snapshot odontogram menyimpan kode itu.
 */
final class ToothConditionEntity extends EloquentMasterEntity
{
    public function key(): string
    {
        return 'tooth-conditions';
    }

    public function title(): string
    {
        return 'Kondisi gigi (odontogram)';
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
            new MasterField('code', 'Kode', 'text', required: true, max: 10, help: 'Huruf besar 2 sampai 10 karakter. Tidak dapat diubah setelah dibuat.'),
            new MasterField('name', 'Nama kondisi', 'text', required: true, max: 100),
            new MasterField('color', 'Warna', 'text', required: true, max: 7, help: 'Kode warna heksadesimal, contoh #DC2626. Odontogram juga menampilkan kode, bukan warna saja.'),
            new MasterField('scope', 'Berlaku untuk', 'select', required: true, options: [
                ['value' => 'surface', 'label' => 'Per permukaan'],
                ['value' => 'tooth', 'label' => 'Seluruh gigi'],
                ['value' => 'both', 'label' => 'Keduanya'],
            ]),
        ];
    }

    public function columns(): array
    {
        return ['code', 'name', 'color', 'scope'];
    }

    public function rules(?string $ignoreId): array
    {
        $clinicId = app(TenantContext::class)->clinicId();

        return [
            'code' => ['required', 'string', 'regex:/^[A-Z]{2,10}$/', Rule::unique('tooth_conditions', 'code')->where('clinic_id', $clinicId)->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'scope' => ['required', Rule::in(['surface', 'tooth', 'both'])],
        ];
    }

    public function update(string $actorId, string $id, array $data): void
    {
        unset($data['code']);
        parent::update($actorId, $id, $data);
    }

    protected function modelClass(): string
    {
        return ToothConditionModel::class;
    }

    protected function subject(): string
    {
        return 'tooth_condition';
    }

    protected function orderColumn(): string
    {
        return 'code';
    }
}
