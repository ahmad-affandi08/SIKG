<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use LogicException;
use Src\Shared\Application\TenantContext;

/**
 * Untuk model bertabel `clinic_id` (dan `branch_id`): global scope otomatis dan pengisian saat dibuat.
 * Mengabaikan scope (`withoutGlobalScope`) hanya untuk kasus sistem yang ditinjau (mis. pencarian pengguna saat login).
 */
trait BelongsToClinic
{
    public static function bootBelongsToClinic(): void
    {
        static::addGlobalScope(new ClinicScope(branchScoped: static::branchScoped()));

        static::creating(function ($model): void {
            /** @var TenantContext $context */
            $context = app(TenantContext::class);

            if ($model->getAttribute('clinic_id') === null) {
                if ($context->clinicId() === null) {
                    throw new LogicException('Konteks klinik kosong; tidak dapat membuat data bertenant tanpa clinic_id.');
                }
                $model->setAttribute('clinic_id', $context->clinicId());
            }

            if (static::branchScoped() && $model->getAttribute('branch_id') === null && $context->branchId() !== null) {
                $model->setAttribute('branch_id', $context->branchId());
            }
        });
    }

    /**
     * Model yang tabelnya tidak punya branch_id (mis. cabang itu sendiri) mengembalikan false.
     */
    protected static function branchScoped(): bool
    {
        return true;
    }
}
