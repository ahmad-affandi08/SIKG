<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Master;

use Closure;
use Src\Clinic\Contracts\BranchDirectory;
use Src\Clinic\Infrastructure\Eloquent\ChairModel;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Application\Transaction;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * MST-03: kursi atau unit perawatan, dapat dinonaktifkan.
 */
final class ChairEntity extends EloquentMasterEntity
{
    public function __construct(Transaction $tx, AuditLogger $audit, private readonly BranchDirectory $branches)
    {
        parent::__construct($tx, $audit);
    }

    public function key(): string
    {
        return 'chairs';
    }

    public function title(): string
    {
        return 'Kursi perawatan';
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
            new MasterField('name', 'Nama kursi atau unit', 'text', required: true, max: 100),
            new MasterField('branch_id', 'Cabang', 'select', options: array_map(
                fn ($b) => ['value' => $b->id, 'label' => $b->name],
                array_values(array_filter($this->branches->all(), fn ($b) => $b->active)),
            ), help: 'Kosong berarti tersedia di semua cabang.'),
        ];
    }

    public function columns(): array
    {
        return ['name', 'branch_id'];
    }

    public function rules(?string $ignoreId): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'branch_id' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && $value !== '' && ! $this->branches->exists((string) $value)) {
                    $fail('Cabang tidak ditemukan.');
                }
            }],
        ];
    }

    protected function modelClass(): string
    {
        return ChairModel::class;
    }

    protected function subject(): string
    {
        return 'chair';
    }
}
