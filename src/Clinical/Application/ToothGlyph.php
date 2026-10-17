<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

/**
 * Gambar satu gigi untuk cetak odontogram: persegi luar, persegi dalam (permukaan oklusal), dan empat bidang di antaranya
 * (bukal atau labial di atas, lingual atau palatal di bawah, mesial dan distal di kiri dan kanan sesuai letak gigi).
 * Kode kondisi dicetak di dalam bidangnya agar tetap terbaca pada cetakan hitam putih. Hasilnya data URI SVG.
 */
final class ToothGlyph
{
    private const OUTER = [2, 38];

    private const INNER = [13, 27];

    /**
     * @param  array{mesial_right: bool, whole: string|null, whole_color: string|null, surfaces: array<string, array{code: string, color: string}>}  $tooth
     */
    public static function dataUri(array $tooth): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($tooth));
    }

    /**
     * @param  array{mesial_right: bool, whole: string|null, whole_color: string|null, surfaces: array<string, array{code: string, color: string}>}  $tooth
     */
    public static function svg(array $tooth): string
    {
        [$o1, $o2] = self::OUTER;
        [$i1, $i2] = self::INNER;
        $left = $tooth['mesial_right'] ? 'D' : 'M';
        $right = $tooth['mesial_right'] ? 'M' : 'D';

        $zones = [
            ['B', "{$o1},{$o1} {$o2},{$o1} {$i2},{$i1} {$i1},{$i1}", 20, 9.2, 4.4],
            ['L', "{$o1},{$o2} {$o2},{$o2} {$i2},{$i2} {$i1},{$i2}", 20, 33.8, 4.4],
            [$left, "{$o1},{$o1} {$i1},{$i1} {$i1},{$i2} {$o1},{$o2}", 7.5, 21.4, 4.4],
            [$right, "{$o2},{$o1} {$i2},{$i1} {$i2},{$i2} {$o2},{$o2}", 32.5, 21.4, 4.4],
            ['O', "{$i1},{$i1} {$i2},{$i1} {$i2},{$i2} {$i1},{$i2}", 20, 22.4, 6.0],
        ];

        $body = '';
        foreach ($zones as [$key, $points, $x, $y, $size]) {
            $fill = $tooth['whole_color'] ?? ($tooth['surfaces'][$key]['color'] ?? '#FFFFFF');
            $code = $key === 'O' ? ($tooth['whole'] ?? ($tooth['surfaces']['O']['code'] ?? '')) : ($tooth['whole'] === null ? ($tooth['surfaces'][$key]['code'] ?? '') : '');
            $body .= '<polygon points="'.$points.'" fill="'.self::color($fill).'" stroke="#000000" stroke-width="0.7"/>';
            if ($code !== '') {
                $body .= '<text x="'.$x.'" y="'.$y.'" font-size="'.$size.'" font-family="Helvetica" text-anchor="middle" fill="#000000">'.htmlspecialchars($code, ENT_XML1).'</text>';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40">'.$body.'</svg>';
    }

    private static function color(string $value): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? $value : '#9CA3AF';
    }
}
