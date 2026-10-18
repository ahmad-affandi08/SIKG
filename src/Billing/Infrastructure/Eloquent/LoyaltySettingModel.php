<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P3-06: pengaturan poin loyalitas.
 *
 * @property bool $enabled
 * @property int $rupiah_per_point
 * @property int $point_value_amount
 * @property int $min_redeem_points
 */
final class LoyaltySettingModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'loyalty_settings';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'rupiah_per_point' => 'integer', 'point_value_amount' => 'integer', 'min_redeem_points' => 'integer'];
    }

    protected static function branchScoped(): bool
    {
        return false;
    }
}
