<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $clinic_id
 * @property string|null $branch_id
 * @property Carbon $date
 * @property string $name
 */
final class HolidayModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'holidays';

    protected $fillable = ['date', 'name', 'branch_id'];

    /**
     * Hari libur berlaku untuk seluruh klinik (atau satu cabang); tidak dibatasi cabang pengguna.
     */
    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
