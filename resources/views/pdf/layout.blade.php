<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @include('pdf.partials.style')
        @yield('style')
    </style>
</head>
<body>
    @unless ($noFooter ?? false)
        <div class="page-footer">
            <table>
                <tr>
                    <td>@yield('footer-left', $letterhead['name'])</td>
                    <td class="r"></td>
                </tr>
            </table>
        </div>
    @endunless

    @include('pdf.partials.letterhead')
    @hasSection('heading')
        <div class="title">@yield('heading')</div>
        @hasSection('subheading')<div class="subtitle">@yield('subheading')</div>@endif
        <div class="head-gap"></div>
    @endif
    @yield('content')
</body>
</html>
