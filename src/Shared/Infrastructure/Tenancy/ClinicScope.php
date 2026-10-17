<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Src\Shared\Application\TenantContext;

/**
 * Membatasi query ke klinik (dan cabang, bila pengguna terikat cabang). Gagal tertutup bila konteks kosong.
 *
 * @implements Scope<Model>
 */
final class ClinicScope implements Scope
{
    public function __construct(private readonly bool $branchScoped = true) {}

    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);
        $table = $model->getTable();

        $clinicId = $context->clinicId();
        if ($clinicId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where("{$table}.clinic_id", $clinicId);

        $branchId = $context->branchId();
        if ($this->branchScoped && $branchId !== null) {
            $builder->where("{$table}.branch_id", $branchId);
        }
    }
}
