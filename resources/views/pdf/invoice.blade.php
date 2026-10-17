@php
    $rp = fn (int $n): string => ($n < 0 ? '-' : '').'Rp '.number_format(abs($n), 0, ',', '.');
    $i = $invoice;
    $isReceipt = $doc === 'receipt';
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $isReceipt ? 'Kwitansi' : 'Invoice' }}</title>
    <style>
        @page { margin: {{ $thermal ? '8px' : '28px 32px' }}; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $thermal ? '8px' : '11px' }}; color: #111827; }
        .kop { border-bottom: 2px solid #111827; padding-bottom: 6px; margin-bottom: 10px; {{ $thermal ? 'text-align: center;' : '' }} }
        .kop img { height: {{ $thermal ? '30px' : '48px' }}; {{ $thermal ? '' : 'float: left; margin-right: 10px;' }} }
        .kop h1 { font-size: {{ $thermal ? '11px' : '16px' }}; margin: 0; }
        .kop p { margin: 2px 0; }
        h2 { font-size: {{ $thermal ? '10px' : '14px' }}; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        table.meta td { padding: 1px 6px 1px 0; vertical-align: top; }
        table.rows th, table.rows td { border-bottom: 1px solid #d1d5db; padding: {{ $thermal ? '2px' : '4px' }}; text-align: left; vertical-align: top; }
        .r { text-align: right !important; }
        .total td { font-weight: bold; border-bottom: 0 !important; }
        .void { color: #b91c1c; font-weight: bold; font-size: 14px; }
        .ttd { margin-top: 36px; float: right; text-align: center; width: 180px; }
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

    @if ($i['status'] === 'void')<p class="void">TAGIHAN DIBATALKAN</p>@endif

    @if ($isReceipt)
        <h2>KWITANSI {{ $receipt['receipt_no'] }}</h2>
        <table class="meta">
            <tr><td>Tanggal</td><td>: {{ $receipt['paid_at_local'] }}</td></tr>
            <tr><td>Telah terima dari</td><td>: {{ $i['patient_name'] ?? '-' }} ({{ $i['medical_record_no'] ?? '-' }})</td></tr>
            <tr><td>Untuk tagihan</td><td>: {{ $i['number'] ?? 'Draft' }}</td></tr>
            <tr><td>{{ $receipt['type'] === 'dp' ? 'Uang muka' : 'Pembayaran' }}</td><td>: <strong>{{ $rp($receipt['amount']) }}</strong> ({{ $receipt['method_name'] ?? '-' }}{{ $receipt['reference_no'] ? ', ref. '.$receipt['reference_no'] : '' }})</td></tr>
            <tr><td>Total tagihan</td><td>: {{ $rp($i['total_amount']) }}</td></tr>
            <tr><td>Sudah dibayar</td><td>: {{ $rp($i['paid_amount']) }}</td></tr>
            <tr><td>Sisa tagihan</td><td>: {{ $rp($i['balance_amount']) }}</td></tr>
            <tr><td>Penerima</td><td>: {{ $receipt['received_by_name'] ?? '-' }}</td></tr>
        </table>
    @else
        <h2>INVOICE {{ $i['number'] }}</h2>
        <table class="meta">
            <tr><td>Tanggal terbit</td><td>: {{ $i['issued_at_local'] }}</td></tr>
            <tr><td>Pasien</td><td>: {{ $i['patient_name'] ?? '-' }} ({{ $i['medical_record_no'] ?? '-' }})</td></tr>
            <tr><td>Status</td><td>: {{ $i['status_label'] }}</td></tr>
        </table>

        <table class="rows" style="margin-top: 8px;">
            <thead>
                <tr><th>Uraian</th><th class="r">Jml</th><th class="r">Harga</th><th class="r">Diskon</th><th class="r">Total</th></tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item['description'] }}@if ($item['discount_reason'])<br><small>Diskon: {{ $item['discount_reason'] }}</small>@endif</td>
                        <td class="r">{{ $item['quantity'] }}</td>
                        <td class="r">{{ $rp($item['unit_price_amount']) }}</td>
                        <td class="r">{{ $item['discount_amount'] > 0 ? $rp($item['discount_amount']) : '-' }}</td>
                        <td class="r">{{ $rp($item['total_amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="r">Subtotal</td><td class="r">{{ $rp($i['subtotal_amount']) }}</td></tr>
                @if ($i['discount_amount'] > 0)
                    <tr><td colspan="4" class="r">Diskon tagihan{{ $i['discount_reason'] ? ' ('.$i['discount_reason'].')' : '' }}</td><td class="r">-{{ $rp($i['discount_amount']) }}</td></tr>
                @endif
                <tr class="total"><td colspan="4" class="r">TOTAL</td><td class="r">{{ $rp($i['total_amount']) }}</td></tr>
                <tr><td colspan="4" class="r">Dibayar</td><td class="r">{{ $rp($i['paid_amount']) }}</td></tr>
                <tr class="total"><td colspan="4" class="r">Sisa</td><td class="r">{{ $rp($i['balance_amount']) }}</td></tr>
            </tfoot>
        </table>

        @if (count($payments) > 0)
            <h2>Pembayaran</h2>
            <table class="rows">
                <tbody>
                    @foreach ($payments as $p)
                        <tr>
                            <td>{{ $p['paid_at_local'] }}</td>
                            <td>{{ $p['type_label'] }} - {{ $p['method_name'] ?? '-' }}</td>
                            <td>{{ $p['receipt_no'] ?? '' }}</td>
                            <td class="r">{{ $p['type'] === 'refund' ? '-' : '' }}{{ $rp($p['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <p style="margin-top: 14px;">Terima kasih.</p>
</body>
</html>
