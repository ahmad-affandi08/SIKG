<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Illuminate\Support\Facades\Storage;
use Src\Clinic\Contracts\LoginBrand;
use Src\Clinic\Contracts\LoginBranding;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;

final class EloquentLoginBranding implements LoginBranding
{
    private const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg'];

    public function brand(): ?LoginBrand
    {
        $clinic = $this->onlyClinic();
        if ($clinic === null) {
            return null;
        }

        return new LoginBrand($clinic->name, $this->logoPath($clinic) === null ? null : substr(md5((string) $clinic->logo_path), 0, 10));
    }

    public function logo(): ?array
    {
        $clinic = $this->onlyClinic();
        $path = $clinic === null ? null : $this->logoPath($clinic);
        $mime = $path === null ? null : (self::MIME[pathinfo($path, PATHINFO_EXTENSION)] ?? null);
        if ($path === null || $mime === null) {
            return null;
        }

        return ['contents' => (string) Storage::disk('private')->get($path), 'mime' => $mime];
    }

    /** Halaman masuk berlaku sebelum ada konteks klinik, jadi pelingkupan klinik dilepas dan hanya dipakai bila kliniknya tunggal. */
    private function onlyClinic(): ?ClinicModel
    {
        $clinics = ClinicModel::query()->withoutGlobalScope('klinik-sendiri')->limit(2)->get();

        return $clinics->count() === 1 ? $clinics->first() : null;
    }

    private function logoPath(ClinicModel $clinic): ?string
    {
        $path = $clinic->logo_path;

        return $path !== null && Storage::disk('private')->exists($path) ? $path : null;
    }
}
