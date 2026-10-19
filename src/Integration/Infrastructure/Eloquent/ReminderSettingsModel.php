<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property bool $enabled
 * @property string $mode
 * @property string $provider
 * @property bool $h1_enabled
 * @property bool $h0_enabled
 * @property bool $recall_enabled
 * @property int $recall_months
 * @property string|null $h1_template
 * @property string|null $h0_template
 * @property string|null $recall_template
 * @property string|null $api_phone_number_id
 * @property string|null $api_token_encrypted
 * @property string|null $api_template_h1
 * @property string|null $api_template_h0
 * @property string|null $api_template_recall
 * @property string|null $api_base_url
 * @property string|null $api_session
 * @property string $api_language
 * @property string|null $staff_template
 */
final class ReminderSettingsModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'reminder_settings';

    /** @var list<string> */
    protected $hidden = ['api_token_encrypted'];

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
        return ['enabled' => 'boolean', 'h1_enabled' => 'boolean', 'h0_enabled' => 'boolean', 'recall_enabled' => 'boolean', 'recall_months' => 'integer'];
    }
}
