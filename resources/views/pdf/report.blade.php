@extends('pdf.layout')
@section('title', $title)
@section('footer-left', $title.' · Periode '.$from.($from !== $to ? ' – '.$to : '').' · Dicetak '.$printedAt)
@section('heading', $title)
@section('subheading', 'Periode '.$from.($from !== $to ? ' sampai '.$to : '').' · Dicetak '.$printedAt)
@section('style')
    body { font-size: 9pt; }
    table.data th { font-size: 8pt; }
    table.data td { padding: 3.5pt 5pt; }
    table.metrics { width: 100%; border-collapse: collapse; margin-top: 8pt; }
    table.metrics td { padding: 4pt 6pt; border-bottom: 0.5pt solid #c8c8c8; }
    table.metrics td.v { text-align: right; font-weight: bold; white-space: nowrap; }
    table.metrics tr:first-child td { border-top: 1pt solid #000000; }
    table.metrics tr:last-child td { border-bottom: 1pt solid #000000; }
    ul.notes { margin: 3pt 0 0 12pt; padding: 0; }
    ul.notes li { margin-bottom: 3pt; color: #000000; }
@endsection

@section('content')
    @if (count($metrics) > 0)
        <h2 class="section">Ringkasan</h2>
        <table class="metrics">
            @foreach (array_chunk($metrics, 2) as $pair)
                <tr>
                    <td>{{ $pair[0]['label'] }}</td><td class="v">{{ $pair[0]['value'] }}</td>
                    @if (isset($pair[1]))<td style="padding-left: 18pt;">{{ $pair[1]['label'] }}</td><td class="v">{{ $pair[1]['value'] }}</td>@else<td></td><td></td>@endif
                </tr>
            @endforeach
        </table>
    @endif

    @foreach ($tables as $t)
        <h2 class="section">{{ $t['title'] }}</h2>
        @if (count($t['rows']) === 0)
            <p class="muted">{{ $t['empty'] ?? 'Tidak ada data.' }}</p>
        @else
            <table class="data">
                <thead>
                    <tr>@foreach ($t['headers'] as $h)<th class="{{ $h['right'] ? 'num' : '' }}">{{ $h['label'] }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @foreach ($t['rows'] as $row)
                        <tr>@foreach ($row as $i => $cell)<td class="{{ $t['headers'][$i]['right'] ? 'num' : '' }}">{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                    @if ($t['totals'] !== null)
                        <tr class="total">@foreach ($t['totals'] as $i => $cell)<td class="{{ $t['headers'][$i]['right'] ? 'num' : '' }}">{{ $cell }}</td>@endforeach</tr>
                    @endif
                </tbody>
            </table>
            @if ($t['truncated'])<p class="small muted" style="margin-top: 3pt;">Tabel dipotong pada baris pertama. Persempit rentang tanggal untuk melihat sisanya.</p>@endif
        @endif
    @endforeach

    @if (count($notes) > 0)
        <h2 class="section">Catatan definisi</h2>
        <ul class="notes">@foreach ($notes as $n)<li>{{ $n }}</li>@endforeach</ul>
    @endif

    <table class="sign">
        <tr>
            <td style="width: 34%;"><div class="role">Disusun oleh,</div><div class="space"></div><div class="blank">Nama dan jabatan</div></td>
            <td style="width: 32%;"><div class="role">Diperiksa oleh,</div><div class="space"></div><div class="blank">Nama dan jabatan</div></td>
            <td style="width: 34%;"><div class="role">Mengetahui,</div><div class="space"></div><div class="blank">Pimpinan klinik</div></td>
        </tr>
    </table>

    <p class="small muted" style="margin-top: 10pt;">Laporan ini dibuat otomatis oleh sistem dari data pada saat dicetak.</p>
@endsection
