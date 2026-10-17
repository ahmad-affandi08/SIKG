<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\ClinicScope;

/**
 * @property string $id
 * @property string|null $actor_id
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
final class AuditLogModel extends Model
{
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    /** @var list<string> */
    protected $fillable = ['clinic_id', 'branch_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'ip', 'user_agent', 'created_at'];

    protected static function booted(): void
    {
        // Dibaca per klinik; ditulis oleh sistem (klinik boleh kosong untuk kejadian sebelum login).
        self::addGlobalScope(new ClinicScope);

        self::updating(static function (): never {
            throw new LogicException('Log audit tidak dapat diubah.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Log audit tidak dapat dihapus.');
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
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }
}
