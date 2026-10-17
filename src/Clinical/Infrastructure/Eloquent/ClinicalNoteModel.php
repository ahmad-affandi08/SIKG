<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * Catatan klinis hanya bertambah: tidak ada pembaruan atau penghapusan dari aplikasi (RM-07).
 *
 * @property string $id
 * @property string $encounter_id
 * @property string $type
 * @property string|null $section
 * @property string $body
 * @property string $author_id
 * @property Carbon $authored_at
 * @property string|null $supersedes_id
 * @property string|null $reason
 */
final class ClinicalNoteModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'clinical_notes';

    /** @var list<string> */
    protected $fillable = ['encounter_id', 'type', 'section', 'body', 'author_id', 'authored_at', 'supersedes_id', 'reason', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    protected static function booted(): void
    {
        $refuse = function (): never {
            throw new LogicException('Catatan klinis tidak dapat diubah atau dihapus; buat versi baru atau addendum.');
        };
        self::updating($refuse);
        self::deleting($refuse);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['authored_at' => 'datetime'];
    }
}
