<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Controllers;

use Illuminate\Http\Response;
use Src\Clinic\Contracts\LoginBranding;

/** Logo klinik untuk halaman masuk (publik, hanya gambar logo). 404 bila tidak ada logo atau klinik tidak tunggal. */
final class LoginBrandLogoController
{
    public function __invoke(LoginBranding $branding): Response
    {
        $logo = $branding->logo();
        abort_if($logo === null, 404);

        return response($logo['contents'], 200, [
            'Content-Type' => $logo['mime'],
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
