<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Odontogram {{ $patient['name'] }}</title>
    <style>
        @page { margin: 26px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; }
        .kop { border-bottom: 2px solid #111827; padding-bottom: 6px; margin-bottom: 8px; }
        .kop img { height: 40px; float: left; margin-right: 10px; }
        .kop h1 { font-size: 14px; margin: 0; }
        .kop p { margin: 2px 0; }
        h2 { font-size: 13px; margin: 6px 0 2px; }
        h3 { font-size: 10px; margin: 10px 0 4px; }
        table.meta td { padding: 1px 8px 1px 0; }
        table.arch { margin: 0 auto 4px; border-collapse: separate; border-spacing: 3px; }
        table.arch td.t { text-align: center; vertical-align: top; padding: 0; }
        .n { font-size: 9px; font-weight: bold; margin: 1px 0; }
        table.tooth { border-collapse: collapse; margin: 0 auto; }
        table.tooth td { width: 15px; height: 12px; border: 0.6px solid #374151; padding: 0; font-size: 6px; text-align: center; }
        .gap { height: 8px; }
        table.legend td { padding: 1px 6px 1px 0; }
        .sw { display: inline-block; width: 9px; height: 9px; border: 0.6px solid #374151; }
        .foot { margin-top: 12px; color: #6b7280; font-size: 8px; }
        table.det td { padding: 2px 6px 2px 0; border-bottom: 0.5px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="kop">
        @if ($letterhead['logo'])<img src="{{ $letterhead['logo'] }}" alt="Logo">@endif
        <h1>{{ $letterhead['name'] }}</h1>
        @if ($letterhead['address'])<p>{{ $letterhead['address'] }}</p>@endif
        @if ($letterhead['license'])<p>Izin: {{ $letterhead['license'] }}</p>@endif
        <div style="clear: both;"></div>
    </div>

    <h2>ODONTOGRAM</h2>
    <table class="meta">
        <tr><td>Nama pasien</td><td>: {{ $patient['name'] }}</td><td>Nomor RM</td><td>: {{ $patient['medical_record_no'] }}</td></tr>
        <tr><td>Tanggal lahir</td><td>: {{ $patient['birth_date'] ?? '-' }}</td><td>Dicetak</td><td>: {{ $printed_at }}</td></tr>
    </table>

    @if ($empty)<p>Belum ada kondisi gigi yang dicatat. Semua gigi dianggap sehat.</p>@endif

    @foreach ($rows as $group)
        <h3>{{ $group['title'] }} (penomoran FDI)</h3>
        @foreach (['upper', 'lower'] as $arch)
            <table class="arch">
                <tr>
                    @foreach ($group[$arch] as $tooth)
                        @php
                            $s = $tooth['surfaces'];
                            $w = $tooth['whole_color'];
                            $col = fn (string $code) => $w ?? ($s[$code]['color'] ?? '#FFFFFF');
                            $left = $tooth['mesial_right'] ? 'D' : 'M';
                            $right = $tooth['mesial_right'] ? 'M' : 'D';
                        @endphp
                        <td class="t">
                            @if ($arch === 'upper')<div class="n">{{ $tooth['fdi'] }}</div>@endif
                            <table class="tooth">
                                <tr><td style="background: {{ $w ?? '#FFFFFF' }};"></td><td style="background: {{ $col('B') }};"></td><td style="background: {{ $w ?? '#FFFFFF' }};"></td></tr>
                                <tr>
                                    <td style="background: {{ $col($left) }};"></td>
                                    <td style="background: {{ $col('O') }};">@if ($tooth['whole']){{ $tooth['whole'] }}@endif</td>
                                    <td style="background: {{ $col($right) }};"></td>
                                </tr>
                                <tr><td style="background: {{ $w ?? '#FFFFFF' }};"></td><td style="background: {{ $col('L') }};"></td><td style="background: {{ $w ?? '#FFFFFF' }};"></td></tr>
                            </table>
                            @if ($arch === 'lower')<div class="n">{{ $tooth['fdi'] }}</div>@endif
                        </td>
                    @endforeach
                </tr>
            </table>
            @if ($arch === 'upper')<div class="gap"></div>@endif
        @endforeach
    @endforeach
    <p style="color: #6b7280;">Keterangan kotak: atas = bukal atau labial (B), bawah = lingual atau palatal (L), tengah = oklusal atau insisal (O), kiri dan kanan = mesial (M) dan distal (D) sesuai letak gigi. Kotak tengah berisi kode bila seluruh gigi memiliki satu kondisi.</p>

    @if (count($details) > 0)
        <h3>Rincian kondisi</h3>
        <table class="det">
            @foreach ($details as $d)
                <tr><td><strong>{{ $d['fdi'] }}</strong></td><td>{{ $d['text'] }}</td></tr>
            @endforeach
        </table>
    @endif

    <h3>Daftar kondisi</h3>
    <table class="legend">
        @foreach (array_chunk($legend, 3) as $chunk)
            <tr>
                @foreach ($chunk as $c)
                    <td><span class="sw" style="background: {{ $c['color'] }};"></span> {{ $c['code'] }} {{ $c['name'] }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <p class="foot">Odontogram terkini dihitung dari seluruh kunjungan pasien sampai saat dicetak.</p>
</body>
</html>
