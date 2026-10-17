<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Eloquent;

use Illuminate\Support\Str;

/**
 * Kunci primer ULID CHAR(26) huruf besar (ADR-0004). HasUlids bawaan Laravel membuat huruf kecil.
 */
trait UsesUlid
{
    public static function bootUsesUlid(): void
    {
        static::creating(function ($model): void {
            $key = $model->getKeyName();
            if ($model->getAttribute($key) === null || $model->getAttribute($key) === '') {
                $model->setAttribute($key, strtoupper((string) Str::ulid()));
            }
        });
    }

    public function initializeUsesUlid(): void
    {
        $this->keyType = 'string';
        $this->incrementing = false;
    }
}
