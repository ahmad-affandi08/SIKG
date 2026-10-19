<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Identitas klinik untuk halaman masuk (sebelum ada sesi, jadi tanpa konteks klinik). Hanya nama dan logo, tanpa data pasien.
 * Diisi hanya bila basis data ini memuat tepat SATU klinik; dengan lebih dari satu klinik halaman masuk tetap netral.
 */
interface LoginBranding
{
    public function brand(): ?LoginBrand;

    /** @return array{contents: string, mime: string}|null */
    public function logo(): ?array;
}
