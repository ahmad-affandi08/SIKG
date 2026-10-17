<?php

declare(strict_types=1);

namespace Src\Insurance\Infrastructure\Eloquent;

use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * @property string $patient_id
 * @property string $insurer_id
 * @property string $policy_no
 * @property int|null $ceiling_amount
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property string|null $notes
 */
final class PatientPolicyModel extends TenantMasterModel
{
    protected $table = 'patient_policies';

    /** @var list<string> */
    protected $fillable = ['patient_id', 'insurer_id', 'policy_no', 'ceiling_amount', 'valid_from', 'valid_to', 'notes', 'branch_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'ceiling_amount' => 'integer', 'valid_from' => 'date', 'valid_to' => 'date'];
    }
}
