<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Resep</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        .kop { border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 12px; }
        .kop img { height: 48px; float: left; margin-right: 10px; }
        .kop h1 { font-size: 16px; margin: 0; }
        .kop p { margin: 2px 0; }
        table.meta td { padding: 1px 6px 1px 0; vertical-align: top; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th, table.items td { border-bottom: 1px solid #d1d5db; padding: 4px; text-align: left; vertical-align: top; }
        .ttd { margin-top: 40px; float: right; text-align: center; width: 200px; }
        .void { color: #b91c1c; font-weight: bold; font-size: 14px; }
    </style>
</head>
<body>
    <div class="kop">
        @if ($letterhead['logo'])<img src="{{ $letterhead['logo'] }}" alt="Logo">@endif
        <h1>{{ $letterhead['name'] }}</h1>
        @if ($letterhead['address'])<p>{{ $letterhead['address'] }}</p>@endif
        @if ($letterhead['license'])<p>Izin: {{ $letterhead['license'] }}</p>@endif
        @if ($letterhead['header'])<p>{!! nl2br(e($letterhead['header'])) !!}</p>@endif
        <div style="clear: both;"></div>
    </div>

    @if ($void)<p class="void">RESEP DIBATALKAN</p>@endif

    <table class="meta">
        <tr><td>Tanggal</td><td>: {{ $issuedAt }}</td></tr>
        <tr><td>Pasien</td><td>: {{ $patient['name'] }} ({{ $patient['mrn'] }}){{ $patient['age'] !== null ? ', '.$patient['age'].' tahun' : '' }}</td></tr>
        <tr><td>Dokter</td><td>: {{ $doctor['name'] }}{{ $doctor['sip'] ? ' (SIP '.$doctor['sip'].')' : '' }}</td></tr>
    </table>

    <table class="items">
        <thead>
            <tr><th>R/ Obat</th><th>Jumlah</th><th>Aturan pakai</th></tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td>{{ $item['dose'] }}, {{ $item['frequency'] }}, {{ $item['duration'] }}@if ($item['instructions']) - {{ $item['instructions'] }}@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="ttd">
        <p>{{ $doctor['name'] }}</p>
        <p style="margin-top: 40px;">(tanda tangan dan stempel)</p>
    </div>
</body>
</html>
