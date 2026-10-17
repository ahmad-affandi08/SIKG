<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * @property string $name
 * @property string|null $sip_no
 * @property string|null $specialty
 * @property string|null $user_id
 * @property array<string, mixed>|null $fee_scheme
 */
final class DoctorModel extends TenantMasterModel
{
    protected $table = 'doctors';

    /** @var list<string> */
    protected $fillable = ['name', 'sip_no', 'specialty', 'branch_id', 'user_id', 'fee_scheme'];

    /** @var array<string, string> */
    protected $casts = ['is_active' => 'boolean', 'fee_scheme' => 'array'];

    /**
     * @return HasMany<DoctorScheduleModel, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorScheduleModel::class, 'doctor_id');
    }
}
