<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\ClinicScope;

/**
 * @property string $id
 * @property string|null $actor_id
 * @property string|null $patient_id
 * @property string $route
 */
final class AccessLogModel extends Model
{
    use UsesUlid;

    public const CREATED_AT = 'at';

    public const UPDATED_AT = null;

    protected $table = 'access_logs';

    /** @var list<string> */
    protected $fillable = ['clinic_id', 'branch_id', 'actor_id', 'patient_id', 'route', 'ip', 'at'];

    protected static function booted(): void
    {
        self::addGlobalScope(new ClinicScope);

        self::updating(static function (): never {
            throw new LogicException('Log akses tidak dapat diubah.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Log akses tidak dapat dihapus.');
        });
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder($query): ImmutableLogBuilder
    {
        return new ImmutableLogBuilder($query);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['at' => 'datetime'];
    }
}
