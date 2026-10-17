<?php

declare(strict_types=1);

namespace Src\Lab\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $order_no
 * @property string $patient_id
 * @property string $doctor_id
 * @property string $laboratory_id
 * @property string $work_type
 * @property string $tooth_notes
 * @property string $description
 * @property string|null $shade
 * @property string $status
 * @property Carbon $ordered_date
 * @property Carbon|null $due_date
 * @property Carbon|null $sent_date
 * @property Carbon|null $received_date
 * @property int $lab_cost_amount
 * @property int $patient_charge_amount
 * @property string|null $invoice_id
 * @property string|null $cancel_note
 * @property int $version
 */
final class LabOrderModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'lab_orders';

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
        return ['ordered_date' => 'date', 'due_date' => 'date', 'sent_date' => 'date', 'received_date' => 'date', 'lab_cost_amount' => 'integer', 'patient_charge_amount' => 'integer', 'version' => 'integer'];
    }
}
