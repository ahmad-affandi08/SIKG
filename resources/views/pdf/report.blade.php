<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $code }} {{ $title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; }
        .kop { border-bottom: 2px solid #111827; padding-bottom: 6px; margin-bottom: 10px; }
        .kop img { height: 40px; float: left; margin-right: 10px; }
        .kop h1 { font-size: 14px; margin: 0; }
        .kop p { margin: 2px 0; }
        h2 { font-size: 13px; margin: 8px 0 2px; }
        h3 { font-size: 10px; margin: 14px 0 4px; }
        .meta { color: #4b5563; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        table.metrics td { padding: 3px 6px; border: 1px solid #d1d5db; }
        table.metrics td.v { text-align: right; font-weight: bold; }
        table.rows th { background: #e5e7eb; text-align: left; padding: 4px; border: 1px solid #9ca3af; }
        table.rows td { padding: 3px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .r { text-align: right !important; }
        tr.total td { font-weight: bold; border-top: 1px solid #111827; }
        .empty, .trunc, .note { color: #4b5563; }
        ul.notes { margin: 4px 0 0 14px; padding: 0; }
        .foot { margin-top: 12px; color: #6b7280; font-size: 8px; }
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

    <h2>{{ $code }} {{ $title }}</h2>
    <p class="meta">Periode {{ $from }}@if ($from !== $to) sampai {{ $to }}@endif &middot; Dicetak {{ $printedAt }}</p>

    @if (count($metrics) > 0)
        <table class="metrics">
            @foreach ($metrics as $m)
                <tr><td>{{ $m['label'] }}</td><td class="v">{{ $m['value'] }}</td></tr>
            @endforeach
        </table>
    @endif

    @foreach ($tables as $t)
        <h3>{{ $t['title'] }}</h3>
        @if (count($t['rows']) === 0)
            <p class="empty">{{ $t['empty'] ?? 'Tidak ada data.' }}</p>
        @else
            <table class="rows">
                <thead>
                    <tr>@foreach ($t['headers'] as $h)<th class="{{ $h['right'] ? 'r' : '' }}">{{ $h['label'] }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @foreach ($t['rows'] as $row)
                        <tr>@foreach ($row as $i => $cell)<td class="{{ $t['headers'][$i]['right'] ? 'r' : '' }}">{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                    @if ($t['totals'] !== null)
                        <tr class="total">@foreach ($t['totals'] as $i => $cell)<td class="{{ $t['headers'][$i]['right'] ? 'r' : '' }}">{{ $cell }}</td>@endforeach</tr>
                    @endif
                </tbody>
            </table>
            @if ($t['truncated'])<p class="trunc">Tabel dipotong pada baris pertama. Persempit rentang tanggal untuk melihat sisanya.</p>@endif
        @endif
    @endforeach

    @if (count($notes) > 0)
        <h3>Catatan</h3>
        <ul class="notes">@foreach ($notes as $n)<li class="note">{{ $n }}</li>@endforeach</ul>
    @endif

    <p class="foot">Laporan ini dibuat oleh sistem dari data pada saat dicetak.</p>
</body>
</html>
