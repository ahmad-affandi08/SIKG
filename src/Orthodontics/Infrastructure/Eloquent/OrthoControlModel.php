<?php

declare(strict_types=1);

namespace Src\Orthodontics\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $case_id
 * @property Carbon $control_date
 * @property string|null $encounter_id
 * @property string|null $wire_or_tray
 * @property string|null $adjustments
 * @property string|null $notes
 * @property Carbon|null $next_control_date
 * @property string $created_by
 */
final class OrthoControlModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'orthodontic_controls';

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
        return ['control_date' => 'date', 'next_control_date' => 'date'];
    }
}
