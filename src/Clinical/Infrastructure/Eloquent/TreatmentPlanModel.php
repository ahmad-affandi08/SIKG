<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $title
 * @property string $status
 * @property string|null $notes
 * @property Carbon $created_at
 */
final class TreatmentPlanModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'treatment_plans';

    /** @var list<string> */
    protected $fillable = ['patient_id', 'title', 'status', 'notes', 'branch_id'];
}
