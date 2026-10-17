<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $number }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.55; color: #111827; }
        .kop { border-bottom: 2px solid #111827; padding-bottom: 6px; margin-bottom: 14px; }
        .kop img { height: 48px; float: left; margin-right: 10px; }
        .kop h1 { font-size: 16px; margin: 0; }
        .kop p { margin: 2px 0; }
        h2 { text-align: center; font-size: 13px; margin: 6px 0 2px; text-decoration: underline; }
        .no { text-align: center; margin: 0 0 14px; color: #374151; }
        p.par { margin: 0 0 9px; text-align: left; white-space: pre-line; }
        .sign { margin-top: 22px; width: 100%; }
        .sign td { width: 33%; vertical-align: top; text-align: center; padding: 0 8px; }
        .space { height: 62px; }
        .small { font-size: 9px; color: #6b7280; }
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

    <h2>{{ $heading }}</h2>
    <p class="no">Nomor: {{ $number }}</p>

    @foreach ($paragraphs as $paragraph)
        <p class="par">{{ trim($paragraph) }}</p>
    @endforeach

    @if ($patient_signature)
        <p style="text-align: right; margin: 18px 0 0;">{{ $place }}, {{ $issued_on }}</p>
        <table class="sign" style="margin-top: 6px;">
            <tr>
                <td>
                    Pasien atau wali
                    <div class="space"></div>
                    (....................................)
                    <div class="small">Nama jelas dan hubungan dengan pasien</div>
                </td>
                <td>
                    Saksi
                    <div class="space"></div>
                    (....................................)
                    <div class="small">Nama jelas</div>
                </td>
                <td>
                    Dokter yang menjelaskan
                    <div class="space"></div>
                    <strong>{{ $doctor['name'] }}</strong>
                    @if ($doctor['sip'])<div class="small">SIP {{ $doctor['sip'] }}</div>@endif
                </td>
            </tr>
        </table>
    @else
        <table class="sign">
            <tr>
                <td></td>
                <td>
                    {{ $place }}, {{ $issued_on }}<br>
                    Dokter
                    <div class="space"></div>
                    <strong>{{ $doctor['name'] }}</strong><br>
                    @if ($doctor['sip'])<span class="small">SIP {{ $doctor['sip'] }}</span>@endif
                </td>
            </tr>
        </table>
    @endif

    <p class="small" style="margin-top: 18px;">Dokumen ini dicetak dari rekam medis {{ $patient['name'] }} (No. RM {{ $patient['medical_record_no'] }}). Bersifat rahasia.</p>
</body>
</html>
