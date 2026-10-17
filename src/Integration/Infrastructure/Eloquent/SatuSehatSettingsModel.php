<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property bool $enabled
 * @property string $environment
 * @property string|null $organization_id
 * @property string|null $location_id
 * @property string|null $location_name
 * @property string|null $client_id
 * @property string|null $client_secret_encrypted
 */
final class SatuSehatSettingsModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'satusehat_settings';

    /** @var list<string> */
    protected $hidden = ['client_secret_encrypted'];

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
        return ['enabled' => 'boolean'];
    }
}
