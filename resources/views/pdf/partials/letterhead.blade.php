@php($hasLogo = ! empty($letterhead['logo']))
<table class="kop">
    <tr>
        @if ($hasLogo)<td class="logo"><img src="{{ $letterhead['logo'] }}" alt="Logo {{ $letterhead['name'] }}"></td>@endif
        <td class="body">
            <div class="name">{{ $letterhead['name'] }}</div>
            @if (! empty($letterhead['address']))<div class="line">{{ $letterhead['address'] }}</div>@endif
            @if (! empty($letterhead['license']))<div class="line">Izin: {{ $letterhead['license'] }}</div>@endif
            @if (! empty($letterhead['header']))<div class="line">{!! nl2br(e($letterhead['header'])) !!}</div>@endif
        </td>
        @if ($hasLogo)<td class="logo"></td>@endif
    </tr>
    <tr class="rule"><td colspan="{{ $hasLogo ? 3 : 1 }}"></td></tr>
</table>
