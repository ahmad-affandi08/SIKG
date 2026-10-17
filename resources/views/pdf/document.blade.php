@extends('pdf.layout')
@section('title', $heading.' '.$number)
@section('footer-left', $heading.' · No. '.$number.' · '.$patient['name'].' (RM '.$patient['medical_record_no'].')')
@if (! $official_letter)
    @section('heading', $heading)
    @section('subheading', 'Nomor: '.$number)
@endif

@section('content')
    @if ($official_letter)
        <table class="letter-meta">
            <tr>
                <td class="k">Nomor</td><td class="s">:</td><td>{{ $number }}</td>
                <td class="date" rowspan="3">{{ $issued_on }}</td>
            </tr>
            <tr><td class="k">Lampiran</td><td class="s">:</td><td>-</td></tr>
            <tr><td class="k">Perihal</td><td class="s">:</td><td><strong>{{ $subject }}</strong></td></tr>
        </table>
    @endif

    @foreach ($blocks as $block)
        @if ($block['kind'] === 'kv')
            <table class="kv" style="margin: 0 0 7pt 14pt; width: 94%;">
                @foreach ($block['rows'] as [$label, $value])
                    <tr><td class="k" style="width: 36%;">{{ $label }}</td><td class="s">:</td><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        @elseif ($block['kind'] === 'list')
            <ol class="list">@foreach ($block['items'] as $item)<li>{{ $item }}</li>@endforeach</ol>
        @else
            <p class="par"@if (str_contains($block['text'], "\n")) style="text-align: left;"@endif>{{ $block['text'] }}</p>
        @endif
    @endforeach

    @if (! $official_letter)
        <p style="text-align: right; margin-top: 12pt;">Diterbitkan tanggal {{ $issued_on }}</p>
    @endif

    @if ($patient_signature)
        <table class="sign">
            <tr>
                <td style="width: 34%;">
                    <div class="role">Pasien / wali</div>
                    <div class="space"></div>
                    <div class="blank">Nama jelas dan hubungan dengan pasien</div>
                </td>
                <td style="width: 32%;">
                    <div class="role">Saksi</div>
                    <div class="space"></div>
                    <div class="blank">Nama jelas</div>
                </td>
                <td style="width: 34%;">
                    <div class="role">{{ $signer_role }}</div>
                    <div class="space"></div>
                    <div class="name">{{ $doctor['name'] }}</div>
                    @if ($doctor['sip'])<div class="meta">SIP {{ $doctor['sip'] }}</div>@endif
                </td>
            </tr>
        </table>
    @else
        <table class="sign">
            <tr>
                <td style="width: 58%;"></td>
                <td style="width: 42%;">
                    <div class="role">Hormat kami,<br>{{ $signer_role }}</div>
                    <div class="space"></div>
                    <div class="name">{{ $doctor['name'] }}</div>
                    @if ($doctor['sip'])<div class="meta">SIP {{ $doctor['sip'] }}</div>@endif
                </td>
            </tr>
        </table>
    @endif
@endsection
