<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P3-01: pengaturan booking online satu klinik.
 *
 * @property bool $enabled
 * @property string|null $slug
 * @property int $slot_minutes
 * @property int $horizon_days
 * @property int $min_lead_hours
 * @property int $hold_hours
 * @property int $max_active_per_phone
 * @property string|null $otp_template
 * @property string|null $welcome_text
 * @property string $clinic_id
 */
final class BookingSettingModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'booking_settings';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'slot_minutes' => 'integer', 'horizon_days' => 'integer', 'min_lead_hours' => 'integer', 'hold_hours' => 'integer', 'max_active_per_phone' => 'integer'];
    }
}
