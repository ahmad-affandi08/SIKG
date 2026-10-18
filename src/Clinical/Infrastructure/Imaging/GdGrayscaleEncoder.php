<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Imaging;

use RuntimeException;
use Src\Clinical\Application\Imaging\GrayscaleEncoder;

/**
 * PNG skala abu dengan GD (palet 256 abu). GD adalah ekstensi PHP umum di hosting bersama; tanpa itu impor DICOM berhenti dengan pesan jelas.
 */
final class GdGrayscaleEncoder implements GrayscaleEncoder
{
    public function png(string $gray8, int $columns, int $rows): string
    {
        if (! function_exists('imagecreate')) {
            throw new RuntimeException('Ekstensi GD belum aktif; gambar DICOM tidak dapat dikonversi.');
        }
        if ($columns < 1 || $rows < 1) {
            throw new RuntimeException('Ukuran gambar tidak valid.');
        }
        $image = imagecreate($columns, $rows);
        if ($image === false) {
            throw new RuntimeException('Gambar tidak dapat dibuat.');
        }
        $palette = [];
        for ($i = 0; $i < 256; $i++) {
            $palette[$i] = (int) imagecolorallocate($image, $i, $i, $i);
        }
        for ($y = 0; $y < $rows; $y++) {
            $offset = $y * $columns;
            for ($x = 0; $x < $columns; $x++) {
                imagesetpixel($image, $x, $y, $palette[ord($gray8[$offset + $x])]);
            }
        }
        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();

        return $png;
    }
}
