<?php

declare(strict_types=1);

use Src\Clinical\Application\ToothGlyph;

function gigi(array $o = []): array
{
    return ['mesial_right' => false, 'whole' => null, 'whole_color' => null, 'surfaces' => [], ...$o];
}

it('ODO-06 gambar gigi memuat lima bidang: atas, bawah, kiri, kanan, dan persegi tengah', function () {
    $svg = ToothGlyph::svg(gigi());

    expect(substr_count($svg, '<polygon'))->toBe(5)->and($svg)->not->toContain('<text');
});

it('ODO-06 permukaan berkondisi diwarnai dan kodenya dicetak di dalam bidangnya', function () {
    $svg = ToothGlyph::svg(gigi(['surfaces' => ['O' => ['code' => 'KAR', 'color' => '#DC2626'], 'M' => ['code' => 'TAM', 'color' => '#2563EB']]]));

    expect($svg)->toContain('fill="#DC2626"')->and($svg)->toContain('fill="#2563EB"')->and($svg)->toContain('>KAR<')->and($svg)->toContain('>TAM<');
});

it('ODO-06 mesial berada di kiri pada gigi kuadran kiri dan di kanan pada kuadran kanan', function () {
    $kiri = ToothGlyph::svg(gigi(['mesial_right' => false, 'surfaces' => ['M' => ['code' => 'TAM', 'color' => '#2563EB']]]));
    $kanan = ToothGlyph::svg(gigi(['mesial_right' => true, 'surfaces' => ['M' => ['code' => 'TAM', 'color' => '#2563EB']]]));

    preg_match('/<text x="([0-9.]+)"[^>]*>TAM</', $kiri, $a);
    preg_match('/<text x="([0-9.]+)"[^>]*>TAM</', $kanan, $b);
    expect((float) $a[1])->toBeLessThan(20.0)->and((float) $b[1])->toBeGreaterThan(20.0);
});

it('ODO-06 kondisi seluruh gigi mewarnai semua bidang dan kodenya ditulis di tengah', function () {
    $svg = ToothGlyph::svg(gigi(['whole' => 'MHK', 'whole_color' => '#CA8A04']));

    expect(substr_count($svg, 'fill="#CA8A04"'))->toBe(5)->and(substr_count($svg, '>MHK<'))->toBe(1);
});

it('ODO-06 warna yang tidak sah diganti abu-abu dan teks dilepas dari karakter XML', function () {
    $svg = ToothGlyph::svg(gigi(['surfaces' => ['O' => ['code' => 'A&<B', 'color' => 'url(javascript:x)']]]));

    expect($svg)->toContain('fill="#9CA3AF"')->and($svg)->not->toContain('javascript')->and($svg)->toContain('A&amp;&lt;B');
});

it('ODO-06 data URI berupa SVG base64', function () {
    expect(ToothGlyph::dataUri(gigi()))->toStartWith('data:image/svg+xml;base64,');
});
