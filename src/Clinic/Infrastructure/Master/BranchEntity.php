<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Master;

use Closure;
use Src\Clinic\Infrastructure\Eloquent\BranchModel;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * P3-04: cabang klinik. Cabang yang dinonaktifkan tidak dapat dipilih lagi, tetapi datanya tetap ada (M5).
 */
final class BranchEntity extends EloquentMasterEntity
{
    public function key(): string
    {
        return 'branches';
    }

    public function title(): string
    {
        return 'Cabang';
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
            new MasterField('name', 'Nama cabang', 'text', required: true, max: 100),
            new MasterField('address', 'Alamat', 'textarea', max: 255),
        ];
    }

    public function columns(): array
    {
        return ['name', 'address'];
    }

    public function rules(?string $ignoreId): array
    {
        return [
            'name' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail) use ($ignoreId): void {
                $taken = BranchModel::query()->where('name', (string) $value)->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))->exists();
                if ($taken) {
                    $fail('Nama cabang sudah dipakai.');
                }
            }],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function modelClass(): string
    {
        return BranchModel::class;
    }

    protected function subject(): string
    {
        return 'branch';
    }
}
