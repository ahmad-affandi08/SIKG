<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Illuminate\Support\Facades\Storage;
use Src\Clinic\Contracts\Letterhead;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;

final class EloquentLetterheadProvider implements LetterheadProvider
{
    private const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg'];

    public function forDocument(): Letterhead
    {
        $clinic = ClinicModel::query()->firstOrFail();

        return new Letterhead($clinic->name, $clinic->address, $clinic->license_no, $clinic->receipt_header, $this->logo($clinic->logo_path), $clinic->prescription_header);
    }

    private function logo(?string $path): ?string
    {
        $disk = Storage::disk('private');
        if ($path === null || ! $disk->exists($path)) {
            return null;
        }
        $mime = self::MIME[pathinfo($path, PATHINFO_EXTENSION)] ?? null;

        return $mime === null ? null : 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }
}
