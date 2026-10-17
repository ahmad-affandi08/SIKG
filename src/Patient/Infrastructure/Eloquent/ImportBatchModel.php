<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $clinic_id
 * @property string|null $branch_id
 * @property string $file_path
 * @property string $status
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $error_rows
 * @property int $duplicate_rows
 * @property int $imported_rows
 * @property int $skipped_rows
 * @property int $next_row
 * @property bool $include_duplicates
 * @property list<array{row: int, message: string}>|null $errors
 * @property list<int>|null $duplicate_row_numbers
 * @property string|null $failure_reason
 * @property string|null $created_by
 */
final class ImportBatchModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'import_batches';

    /** @var list<string> */
    protected $fillable = ['type', 'file_path', 'status', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'duplicate_row_numbers' => 'array',
            'include_duplicates' => 'boolean',
        ];
    }
}
