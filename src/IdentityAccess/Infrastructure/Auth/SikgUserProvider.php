<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Src\Shared\Infrastructure\Tenancy\ClinicScope;

/**
 * Pemuat pengguna untuk sesi. Konteks klinik belum ada saat sesi dimuat (justru berasal dari pengguna ini),
 * sehingga scope klinik dilepas di sini saja. Pengguna nonaktif atau terhapus tidak pernah dikembalikan.
 */
final class SikgUserProvider extends EloquentUserProvider
{
    /**
     * Pabrik untuk Auth::provider('sikg', ...).
     *
     * @param  array<string, mixed>  $config
     */
    public static function make(Application $app, array $config): self
    {
        $model = $config['model'] ?? null;
        if (! is_string($model) || ! is_subclass_of($model, Authenticatable::class)) {
            throw new InvalidArgumentException('Konfigurasi auth.providers.users.model harus berupa kelas Authenticatable.');
        }

        return new self($app->make('hash'), $model);
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel|null  $model
     * @return Builder<TModel>
     */
    public function newModelQuery($model = null): Builder
    {
        return parent::newModelQuery($model)->withoutGlobalScope(ClinicScope::class);
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        $user = parent::retrieveById($identifier);

        return $user !== null && $user->getAttribute('is_active') === true ? $user : null;
    }
}
