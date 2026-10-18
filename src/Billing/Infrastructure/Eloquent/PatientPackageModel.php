<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P3-06: paket kunjungan milik pasien.
 *
 * @property string $id
 * @property string $patient_id
 * @property string $package_id
 * @property string $name
 * @property int $sessions_total
 * @property int $sessions_left
 * @property int $price_amount
 * @property int|null $max_session_value_amount
 * @property Carbon $purchased_on
 * @property Carbon|null $expires_on
 * @property string|null $invoice_id
 * @property string $status
 */
final class PatientPackageModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'patient_packages';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected function casts(): array
    {
        return ['sessions_total' => 'integer', 'sessions_left' => 'integer', 'price_amount' => 'integer', 'max_session_value_amount' => 'integer', 'purchased_on' => 'date', 'expires_on' => 'date'];
    }

    protected static function branchScoped(): bool
    {
        return false;
    }
}
