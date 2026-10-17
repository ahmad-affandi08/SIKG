<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Backup;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;

/**
 * Catatan jalannya cadangan: hanya bertambah (M5, M10).
 *
 * @property string $id
 * @property string $kind
 * @property bool $ok
 * @property string|null $file
 * @property string $message
 * @property array<string, mixed>|null $details
 * @property Carbon $started_at
 * @property Carbon $finished_at
 */
final class BackupRunModel extends Model
{
    use UsesUlid;

    public $timestamps = false;

    protected $table = 'backup_runs';

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Catatan cadangan tidak dapat diubah.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Catatan cadangan tidak dapat dihapus.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ok' => 'boolean', 'details' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
