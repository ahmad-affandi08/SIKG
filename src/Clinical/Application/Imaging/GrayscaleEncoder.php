<?php

declare(strict_types=1);

namespace Src\Clinical\Application\Imaging;

/**
 * Mengubah piksel abu 8-bit menjadi PNG (P3-03). Implementasi memakai ekstensi GD yang tersedia di hosting bersama.
 */
interface GrayscaleEncoder
{
    /**
     * @param  string  $gray8  rows x columns byte, baris demi baris
     * @return string isi berkas PNG
     */
    public function png(string $gray8, int $columns, int $rows): string;
}
