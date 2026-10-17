<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Clinic\Domain\ClinicSettings;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;

/**
 * @property string $id
 * @property string $name
 * @property string|null $address
 * @property string|null $license_no
 * @property string $timezone
 * @property string|null $logo_path
 * @property array<string, mixed>|null $settings
 */
final class ClinicModel extends Model
{
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'clinics';

    protected $fillable = ['name', 'address', 'license_no', 'timezone', 'logo_path', 'settings'];

    protected static function booted(): void
    {
        // Pengguna hanya melihat kliniknya sendiri; tanpa konteks tidak melihat apa pun (gagal tertutup).
        self::addGlobalScope('klinik-sendiri', function (Builder $builder): void {
            $clinicId = app(TenantContext::class)->clinicId();
            if ($clinicId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }
            $builder->where('clinics.id', $clinicId);
        });
    }

    public function clinicSettings(): ClinicSettings
    {
        return ClinicSettings::fromArray($this->settings);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
