<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Master;

use Src\Billing\Infrastructure\Eloquent\ExpenseCategoryModel;
use Src\Shared\Application\Master\ImportableMaster;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * P2-08: kategori biaya operasional (sewa, listrik, gaji, perlengkapan, dan sejenisnya).
 */
final class ExpenseCategoryEntity extends EloquentMasterEntity implements ImportableMaster
{
    public function importKey(): string
    {
        return 'name';
    }

    public function key(): string
    {
        return 'expense-categories';
    }

    public function title(): string
    {
        return 'Kategori biaya operasional';
    }

    public function viewPermission(): string
    {
        return 'report.financial';
    }

    public function managePermission(): string
    {
        return 'payment.refund';
    }

    public function fields(): array
    {
        return [new MasterField('name', 'Nama kategori', 'text', required: true, max: 100)];
    }

    public function columns(): array
    {
        return ['name'];
    }

    public function rules(?string $ignoreId): array
    {
        return ['name' => ['required', 'string', 'max:100']];
    }

    protected function modelClass(): string
    {
        return ExpenseCategoryModel::class;
    }

    protected function subject(): string
    {
        return 'expense_category';
    }
}
