<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * Pengguna sistem. Dibatasi per klinik oleh global scope; pencarian saat login dan pemuatan sesi
 * memakai UserProvider yang melepas scope itu (belum ada konteks klinik pada saat itu).
 *
 * @property string $id
 * @property string $clinic_id
 * @property string|null $branch_id
 * @property string $name
 * @property string $email
 * @property string $username
 * @property bool $is_active
 * @property bool $must_change_password
 * @property string $password
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret_encrypted
 * @property string|null $two_factor_pending_encrypted
 * @property Carbon|null $two_factor_confirmed_at
 * @property int $two_factor_last_step
 * @property list<string>|null $two_factor_recovery_hashes
 */
class User extends Authenticatable
{
    use BelongsToClinic;
    use HasRoles;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'users';

    protected string $guard_name = 'web';

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'username', 'password', 'is_active', 'branch_id'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret_encrypted', 'two_factor_pending_encrypted', 'two_factor_recovery_hashes'];

    /**
     * Pengguna dikelola di tingkat klinik; cabang boleh kosong (akses semua cabang).
     */
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
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_hashes' => 'array',
            'password' => 'hashed',
        ];
    }
}
