@use('Src\Shared\Domain\Terbilang')
@php
    $rp = fn (int $n): string => ($n < 0 ? '-' : '').'Rp '.number_format(abs($n), 0, ',', '.');
    $i = $invoice;
    $isReceipt = $doc === 'receipt';
    $noFooter = $thermal;
    $docNo = $isReceipt ? $receipt['receipt_no'] : ($i['number'] ?? 'Draft');
@endphp
@extends('pdf.layout')
@section('title', ($isReceipt ? 'Kwitansi ' : 'Invoice ').$docNo)
@section('footer-left', ($isReceipt ? 'Kwitansi ' : 'Invoice ').$docNo.' · '.($i['patient_name'] ?? '-'))
@section('heading', $isReceipt ? 'Kwitansi' : 'Invoice')
@section('subheading', 'Nomor: '.$docNo)
@section('style')
    @if ($thermal)
        @page { margin: 5mm 4mm; }
        body { font-size: 8pt; line-height: 1.35; }
        table.kop td.logo { display: none; width: 0; }
        table.kop .name { font-size: 10.5pt; }
        table.kop .line { font-size: 7pt; }
        .title { font-size: 10.5pt; }
        table.data th, table.data td { padding: 2pt 2pt; font-size: 7.5pt; }
        table.sum { width: 100%; margin-left: 0; }
        table.kv td.k { width: 36%; }
        .sign .space { height: 26pt; }
    @endif
    .amount { font-size: {{ $thermal ? '13pt' : '17pt' }}; font-weight: bold; text-align: center; }
@endsection

@section('content')
    @if ($i['status'] === 'void')<div style="text-align: center; margin-bottom: 8pt;"><span class="stamp">{{ $isReceipt ? 'KWITANSI' : 'TAGIHAN' }} DIBATALKAN</span></div>@endif

    @if ($isReceipt)
        <table class="kv">
            <tr><td class="k">Telah terima dari</td><td class="s">:</td><td><strong>{{ $i['patient_name'] ?? '-' }}</strong> (RM {{ $i['medical_record_no'] ?? '-' }})</td></tr>
            <tr><td class="k">Uang sejumlah</td><td class="s">:</td><td><em>{{ ucfirst(Terbilang::rupiah($receipt['amount'])) }}</em></td></tr>
            <tr><td class="k">Untuk pembayaran</td><td class="s">:</td><td>{{ $receipt['type'] === 'dp' ? 'Uang muka' : 'Pembayaran' }} tagihan {{ $i['number'] ?? 'Draft' }}, {{ $receipt['method_name'] ?? '-' }}{{ $receipt['reference_no'] ? ' (ref. '.$receipt['reference_no'].')' : '' }}</td></tr>
            <tr><td class="k">Tanggal pembayaran</td><td class="s">:</td><td>{{ $receipt['paid_at_local'] }}</td></tr>
        </table>

        <div class="box" style="margin-top: 10pt;"><div class="amount">{{ $rp($receipt['amount']) }},-</div></div>

        <table class="sum">
            <tr><td>Total tagihan</td><td class="v">{{ $rp($i['total_amount']) }}</td></tr>
            <tr><td>Sudah dibayar</td><td class="v">{{ $rp($i['paid_amount']) }}</td></tr>
            <tr class="grand"><td>Sisa tagihan</td><td class="v">{{ $rp($i['balance_amount']) }}</td></tr>
        </table>

        <table class="sign">
            <tr>
                <td style="width: 50%; vertical-align: bottom;">
                    @if ($receipt['amount'] >= 5000000 && ! $thermal)
                        <div class="box" style="width: 110pt; margin: 0 auto; padding: 14pt 6pt;"><div class="small">Meterai Rp10.000</div><div class="small muted">(dokumen bernilai Rp5.000.000 atau lebih)</div></div>
                    @endif
                </td>
                <td style="width: 50%;">
                    <div class="role">{{ ! empty($letterhead['city']) ? $letterhead['city'].', ' : '' }}{{ substr($receipt['paid_at_local'], 0, 10) }}<br>Penerima,</div>
                    <div class="space"></div>
                    <div class="name">{{ $receipt['received_by_name'] ?? '-' }}</div>
                    <div class="meta">Kasir {{ $letterhead['name'] }}</div>
                </td>
            </tr>
        </table>
    @else
        <table class="kv">
            <tr><td class="k">Ditagihkan kepada</td><td class="s">:</td><td><strong>{{ $i['patient_name'] ?? '-' }}</strong> (RM {{ $i['medical_record_no'] ?? '-' }})</td></tr>
            <tr><td class="k">Tanggal terbit</td><td class="s">:</td><td>{{ $i['issued_at_local'] ?? '-' }}</td></tr>
            <tr><td class="k">Status</td><td class="s">:</td><td>{{ $i['status_label'] }}</td></tr>
        </table>

        <h2 class="section">Rincian biaya</h2>
        <table class="data">
            <thead>
                <tr>
                    <th class="no">No</th>
                    <th>Uraian</th>
                    <th class="num" style="width: 8%;">Jml</th>
                    <th class="num">Harga</th>
                    @unless ($thermal)<th class="num">Diskon</th>@endunless
                    <th class="num">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $n => $item)
                    <tr>
                        <td class="no">{{ $n + 1 }}</td>
                        <td>{{ $item['description'] }}@if ($item['discount_reason'])<div class="note">Diskon: {{ $item['discount_reason'] }}</div>@endif @if ($thermal && $item['discount_amount'] > 0)<div class="note">Diskon {{ $rp($item['discount_amount']) }}</div>@endif</td>
                        <td class="num">{{ $item['quantity'] }}</td>
                        <td class="num">{{ $rp($item['unit_price_amount']) }}</td>
                        @unless ($thermal)<td class="num">{{ $item['discount_amount'] > 0 ? $rp($item['discount_amount']) : '-' }}</td>@endunless
                        <td class="num">{{ $rp($item['total_amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="sum">
            <tr><td>Subtotal</td><td class="v">{{ $rp($i['subtotal_amount']) }}</td></tr>
            @if ($i['discount_amount'] > 0)
                <tr><td>Diskon tagihan{{ $i['discount_reason'] ? ' ('.$i['discount_reason'].')' : '' }}</td><td class="v">-{{ $rp($i['discount_amount']) }}</td></tr>
            @endif
            @if ($i['tax_amount'] > 0)
                <tr><td>Pajak ({{ rtrim(rtrim(number_format($i['tax_basis_points'] / 100, 2, ',', '.'), '0'), ',') }}%)</td><td class="v">{{ $rp($i['tax_amount']) }}</td></tr>
            @endif
            <tr class="grand"><td>Total</td><td class="v">{{ $rp($i['total_amount']) }}</td></tr>
            <tr><td>Sudah dibayar</td><td class="v">{{ $rp($i['paid_amount']) }}</td></tr>
            <tr><td class="b">Sisa tagihan</td><td class="v b">{{ $rp($i['balance_amount']) }}</td></tr>
        </table>

        <p class="terbilang">Terbilang: <b>{{ ucfirst(Terbilang::rupiah($i['total_amount'])) }}</b></p>

        @if (count($payments) > 0)
            <h2 class="section">Riwayat pembayaran</h2>
            <table class="data">
                <thead>
                    <tr><th>Tanggal</th><th>Jenis dan metode</th><th>Kwitansi</th><th class="num">Jumlah</th></tr>
                </thead>
                <tbody>
                    @foreach ($payments as $p)
                        <tr>
                            <td>{{ $p['paid_at_local'] }}</td>
                            <td>{{ $p['type_label'] }} · {{ $p['method_name'] ?? '-' }}</td>
                            <td>{{ $p['receipt_no'] ?? '-' }}</td>
                            <td class="num">{{ $p['type'] === 'refund' ? '-' : '' }}{{ $rp($p['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <table class="sign">
            <tr>
                <td style="width: 58%;"></td>
                <td style="width: 42%;">
                    <div class="role">{{ ! empty($letterhead['city']) ? $letterhead['city'].', ' : '' }}{{ substr((string) ($i['issued_at_local'] ?? ''), 0, 10) }}<br>Hormat kami,<br>{{ $letterhead['name'] }}</div>
                    <div class="space"></div>
                    <div class="blank">Kasir</div>
                </td>
            </tr>
        </table>
    @endif

    @if ($isReceipt && ! empty($letterhead['footnote']))<p class="small muted" style="margin-top: 10pt;">{!! nl2br(e($letterhead['footnote'])) !!}</p>@endif
    <p class="small muted" style="margin-top: 12pt;">Terima kasih atas kepercayaan Anda. Simpan dokumen ini sebagai bukti.</p>
@endsection
