@php
    $compactKop = true;
    $jaws = ['upper' => 'Rahang atas', 'lower' => 'Rahang bawah'];
@endphp
@extends('pdf.layout')
@section('title', 'Odontogram '.$patient['name'])
@section('heading', 'Odontogram')
@section('subheading', 'Kondisi gigi terkini · penomoran FDI')
@section('footer-left', 'Odontogram · '.$patient['name'].' (RM '.$patient['medical_record_no'].') · Dicetak '.$printed_at)
@section('style')
    body { font-size: 8.5pt; }
    table.arch { margin: 0 auto 2pt; border-collapse: separate; border-spacing: 2pt 0; }
    table.arch td.t { text-align: center; vertical-align: top; padding: 0; }
    .n { font-size: 8.5pt; font-weight: bold; margin: 1pt 0; }
    table.tooth { border-collapse: collapse; margin: 0 auto; }
    table.tooth td { width: 14pt; height: 13pt; border: 0.6pt solid #000000; padding: 0; font-size: 6pt; line-height: 13pt; text-align: center; color: #000000; }
    .gap { height: 8pt; }
    .jaw { text-align: center; font-size: 7.5pt; letter-spacing: 0.5pt; color: #444444; margin: 2pt 0 3pt; text-transform: uppercase; }
    table.legend { width: 100%; border-collapse: collapse; }
    table.legend td { padding: 2pt 6pt 2pt 0; width: 33%; }
    .sw { display: inline-block; width: 9pt; height: 9pt; border: 0.6pt solid #000000; }
    table.det td { padding: 2.5pt 8pt 2.5pt 0; border-bottom: 0.5pt solid #c8c8c8; vertical-align: top; }
@endsection

@section('content')
    <table class="kv">
        <tr><td class="k">Nama pasien</td><td class="s">:</td><td style="width: 30%;"><strong>{{ $patient['name'] }}</strong></td><td class="k" style="width: 14%;">Nomor RM</td><td class="s">:</td><td>{{ $patient['medical_record_no'] }}</td></tr>
        <tr><td class="k">Tanggal lahir</td><td class="s">:</td><td>{{ $patient['birth_date'] ?? '-' }}</td><td class="k">Dicetak</td><td class="s">:</td><td>{{ $printed_at }}</td></tr>
    </table>

    @if ($empty)<p class="muted" style="margin-top: 8pt;">Belum ada kondisi gigi yang dicatat. Semua gigi dianggap sehat.</p>@endif

    @foreach ($rows as $group)
        <h2 class="section">{{ $group['title'] }}</h2>
        @foreach ($jaws as $arch => $jaw)
            <div class="jaw">{{ $jaw }}</div>
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
                                <tr><td style="background: {{ $w ?? '#FFFFFF' }};"></td><td style="background: {{ $col('B') }};">{{ $s['B']['code'] ?? '' }}</td><td style="background: {{ $w ?? '#FFFFFF' }};"></td></tr>
                                <tr>
                                    <td style="background: {{ $col($left) }};">{{ $s[$left]['code'] ?? '' }}</td>
                                    <td style="background: {{ $col('O') }};">{{ $tooth['whole'] ?? ($s['O']['code'] ?? '') }}</td>
                                    <td style="background: {{ $col($right) }};">{{ $s[$right]['code'] ?? '' }}</td>
                                </tr>
                                <tr><td style="background: {{ $w ?? '#FFFFFF' }};"></td><td style="background: {{ $col('L') }};">{{ $s['L']['code'] ?? '' }}</td><td style="background: {{ $w ?? '#FFFFFF' }};"></td></tr>
                            </table>
                            @if ($arch === 'lower')<div class="n">{{ $tooth['fdi'] }}</div>@endif
                        </td>
                    @endforeach
                </tr>
            </table>
            @if ($arch === 'upper')<div class="gap"></div>@endif
        @endforeach
    @endforeach

    <p class="small muted" style="margin-top: 6pt;">Cara membaca: kotak atas = bukal atau labial (B), bawah = lingual atau palatal (L), tengah = oklusal atau insisal (O), kiri dan kanan = mesial (M) dan distal (D) sesuai letak gigi. Kotak tengah berisi kode bila seluruh gigi memiliki satu kondisi. Kode kondisi dicetak di tiap kotak sehingga tetap terbaca pada cetakan hitam putih.</p>

    @if (count($details) > 0)
        <h2 class="section">Rincian kondisi</h2>
        <table class="det">
            @foreach ($details as $d)
                <tr><td style="width: 36pt;"><strong>{{ $d['fdi'] }}</strong></td><td>{{ $d['text'] }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2 class="section">Daftar kondisi</h2>
    <table class="legend">
        @foreach (array_chunk($legend, 3) as $chunk)
            <tr>
                @foreach ($chunk as $c)
                    <td><span class="sw" style="background: {{ $c['color'] }};"></span> <strong>{{ $c['code'] }}</strong> {{ $c['name'] }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <table class="sign">
        <tr>
            <td style="width: 58%;"></td>
            <td style="width: 42%;"><div class="role">Dokter pemeriksa,</div><div class="space"></div><div class="blank">Nama dan SIP</div></td>
        </tr>
    </table>

    <p class="small muted" style="margin-top: 8pt;">Odontogram terkini dihitung dari seluruh kunjungan pasien sampai saat dicetak.</p>
@endsection
