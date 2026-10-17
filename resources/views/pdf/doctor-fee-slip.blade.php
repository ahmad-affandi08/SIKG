@php
    $rp = fn (int $n): string => ($n < 0 ? '-' : '').'Rp '.number_format(abs($n), 0, ',', '.');
    $bulan = fn (string $ym): string => ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'][substr($ym, 5, 2)].' '.substr($ym, 0, 4);
@endphp
@extends('pdf.layout')
@section('title', 'Slip jasa dokter '.$doctor['name'])
@section('footer-left', 'Slip jasa dokter · '.$doctor['name'].' · Periode '.$from.' sampai '.$to.' · Dicetak '.$printed_at)
@section('heading', 'SLIP JASA DOKTER')
@section('subheading', 'Periode '.$from.' sampai '.$to)
@section('style')
    body { font-size: 9.5pt; }
    table.data td { padding: 3.5pt 5pt; }
    table.who td { padding: 1.5pt 4pt; }
    table.sum td { padding: 3.5pt 6pt; border-bottom: 0.5pt solid #c8c8c8; }
    table.sum td.v { text-align: right; white-space: nowrap; }
    table.sum tr.net td { font-weight: bold; border-top: 1pt solid #000000; border-bottom: 1pt solid #000000; }
@endsection

@section('content')
    <table class="who">
        <tr><td style="width: 22%;">Nama dokter</td><td style="width: 2%;">:</td><td><strong>{{ $doctor['name'] }}</strong></td></tr>
        @if ($doctor['sip'])<tr><td>SIP</td><td>:</td><td>{{ $doctor['sip'] }}</td></tr>@endif
        <tr><td>Skema jasa</td><td>:</td><td>{{ $scheme }}</td></tr>
    </table>

    @foreach ($months as $m)
        <h2 class="section">{{ $bulan($m['month']) }}</h2>
        @if (count($m['lines']) > 0)
            <table class="data">
                <thead>
                    <tr><th>Kode</th><th>Tindakan</th><th class="num">Jumlah</th><th class="num">Nilai tindakan</th><th>Aturan jasa</th><th class="num">Jasa kotor</th></tr>
                </thead>
                <tbody>
                    @foreach ($m['lines'] as $l)
                        <tr>
                            <td>{{ $l['code'] }}</td><td>{{ $l['name'] }}</td><td class="num">{{ $l['qty'] }}</td>
                            <td class="num">{{ $rp($l['base']) }}</td><td>{{ $l['rule'] }}</td><td class="num">{{ $rp($l['fee']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total"><td colspan="3">Jumlah bulan ini</td><td class="num">{{ $rp($m['base']) }}</td><td></td><td class="num">{{ $rp($m['gross']) }}</td></tr>
                </tbody>
            </table>
        @else
            <p class="muted">Tidak ada tindakan pada tagihan final bulan ini.</p>
        @endif
        <table class="sum" style="width: 62%; margin-top: 4pt;">
            <tr><td>Jasa kotor</td><td class="v">{{ $rp($m['gross']) }}</td></tr>
            @if ($m['lab_cost'] > 0 || $m['lab'] > 0)
                <tr><td>Biaya lab diterima bulan ini {{ $rp($m['lab_cost']) }}, potongan</td><td class="v">- {{ $rp($m['lab']) }}</td></tr>
            @endif
            <tr class="net"><td>Jasa bersih bulan ini</td><td class="v">{{ $rp($m['net']) }}</td></tr>
        </table>
        @if ($m['uncovered'] > 0)
            <p class="small muted">Potongan lab {{ $rp($m['uncovered']) }} melebihi jasa bulan ini dan tidak dibawa ke bulan berikutnya.</p>
        @endif
    @endforeach

    <h2 class="section">Total periode</h2>
    <table class="sum" style="width: 62%;">
        <tr><td>Nilai tindakan</td><td class="v">{{ $rp($totals['base']) }}</td></tr>
        <tr><td>Jasa kotor</td><td class="v">{{ $rp($totals['gross']) }}</td></tr>
        <tr><td>Potongan biaya lab</td><td class="v">- {{ $rp($totals['lab']) }}</td></tr>
        <tr class="net"><td>Jasa bersih diterima</td><td class="v">{{ $rp($totals['net']) }}</td></tr>
    </table>

    <table class="sign">
        <tr>
            <td style="width: 50%;"><div class="role">Penerima,</div><div class="space"></div><div class="name">{{ $doctor['name'] }}</div></td>
            <td style="width: 50%;"><div class="role">{{ $place ? $place.', ' : '' }}{{ $printed_at }}<br>Pimpinan klinik,</div><div class="space"></div><div class="blank">Nama dan tanda tangan</div></td>
        </tr>
    </table>
    <p class="small muted" style="margin-top: 8pt;">Slip dibuat otomatis oleh sistem dari tagihan final dan pesanan lab yang diterima pada periode ini; dasar hitung sama dengan laporan Jasa dokter.</p>
@endsection
