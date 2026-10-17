@php
    $noFooter = true;
@endphp
@extends('pdf.layout')
@section('title', 'Resep '.$patient['name'])
@section('heading', 'Resep')
@section('style')
    @page { margin: 12mm 12mm 12mm 12mm; }
    table.kop td.logo { width: 46pt; }
    table.kop td.logo img { max-height: 40pt; max-width: 42pt; }
    table.kop .name { font-size: 12pt; }
    table.kop .line { font-size: 7.5pt; }
    table.rx { width: 100%; border-collapse: collapse; margin-top: 4pt; }
    table.rx td { vertical-align: top; padding: 5pt 0; border-bottom: 0.5pt solid #c8c8c8; }
    table.rx td.sym { width: 26pt; font-size: 15pt; font-weight: bold; line-height: 1.1; }
    .signa { font-style: italic; margin-top: 1pt; }
    .sign .space { height: 34pt; }
@endsection

@section('content')
    @if ($void)<div style="text-align: center; margin-bottom: 6pt;"><span class="stamp">RESEP DIBATALKAN</span></div>@endif

    <table class="kv" style="margin-bottom: 6pt;">
        <tr><td class="k">Dokter</td><td class="s">:</td><td><strong>{{ $doctor['name'] }}</strong>@if ($doctor['sip']) · SIP {{ $doctor['sip'] }}@endif</td></tr>
        <tr><td class="k">Tanggal</td><td class="s">:</td><td>{{ $issuedAt }}</td></tr>
    </table>

    <table class="rx">
        @foreach ($items as $item)
            <tr>
                <td class="sym">R/</td>
                <td>
                    <strong>{{ $item['name'] }}</strong> &nbsp; No. {{ $item['quantity'] }}
                    <div class="signa">S. {{ $item['dose'] }}, {{ $item['frequency'] }}, {{ $item['duration'] }}@if ($item['instructions']) — {{ $item['instructions'] }}@endif</div>
                </td>
            </tr>
        @endforeach
    </table>

    <table class="sign">
        <tr>
            <td style="width: 50%; text-align: left;">
                <div class="small">Pro:</div>
                <div><strong>{{ $patient['name'] }}</strong></div>
                <div class="small">RM {{ $patient['mrn'] }}@if ($patient['age'] !== null) · umur {{ $patient['age'] }} tahun @endif</div>
            </td>
            <td style="width: 50%;">
                <div class="role">Paraf dokter,</div>
                <div class="space"></div>
                <div class="name">{{ $doctor['name'] }}</div>
            </td>
        </tr>
    </table>

    @if (! empty($letterhead['footnote']))<p class="small muted" style="margin-top: 10pt;">{!! nl2br(e($letterhead['footnote'])) !!}</p>@endif
@endsection
