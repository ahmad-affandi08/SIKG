<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $snapshot_id
 * @property int $tooth_fdi
 * @property string $surface
 * @property string $condition_code
 * @property Carbon $set_at
 */
final class OdontogramToothStateModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    protected $table = 'odontogram_tooth_states';

    /** @var list<string> */
    protected $fillable = ['snapshot_id', 'tooth_fdi', 'surface', 'condition_code', 'set_by', 'set_at', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['set_at' => 'datetime'];
    }
}
