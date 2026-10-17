<?php

declare(strict_types=1);

namespace Src\Insurance\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $claim_no
 * @property string $invoice_id
 * @property string $patient_id
 * @property string $insurer_id
 * @property string|null $policy_id
 * @property int $claimed_amount
 * @property int|null $approved_amount
 * @property int $paid_amount
 * @property string $status
 * @property string|null $notes
 * @property string|null $decision_note
 * @property Carbon|null $submitted_at
 * @property Carbon|null $decided_at
 * @property Carbon|null $paid_at
 * @property int $version
 */
final class ClaimModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'insurance_claims';

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
        return ['claimed_amount' => 'integer', 'approved_amount' => 'integer', 'paid_amount' => 'integer', 'version' => 'integer', 'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'paid_at' => 'datetime'];
    }
}
