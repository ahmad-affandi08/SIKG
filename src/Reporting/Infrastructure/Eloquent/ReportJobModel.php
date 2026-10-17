<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $report_key
 * @property string $format
 * @property string $status
 * @property string $scope
 * @property array<string, mixed> $filters
 * @property string $requested_by
 * @property string|null $file_path
 * @property int|null $file_size
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 */
final class ReportJobModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    protected $table = 'report_jobs';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }
}
