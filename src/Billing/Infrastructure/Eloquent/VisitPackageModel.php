<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * P3-06: katalog paket kunjungan.
 *
 * @property string $name
 * @property int $sessions
 * @property int $price_amount
 * @property int|null $max_session_value_amount
 * @property int|null $valid_days
 */
final class VisitPackageModel extends TenantMasterModel
{
    protected $table = 'visit_packages';

    /** @var list<string> */
    protected $fillable = ['name', 'sessions', 'price_amount', 'max_session_value_amount', 'valid_days', 'branch_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sessions' => 'integer', 'price_amount' => 'integer', 'max_session_value_amount' => 'integer', 'valid_days' => 'integer'];
    }
}
