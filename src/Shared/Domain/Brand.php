<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Nama merek yang tampil ke pengguna (antarmuka, cetakan, berkas ekspor, pesan WhatsApp, aplikasi autentikator). Satu tempat
 * agar penggantian nama berikutnya cukup di sini dan di `resources/js/lib/brand.ts`. Identitas teknis (perintah `sikg:*`,
 * variabel `SIKG_*`, `config/sikg.php`, format cadangan `.sikgbak`) sengaja tidak mengikuti nama ini (ADR-0014).
 */
final class Brand
{
    public const NAME = 'SIKLIN';

    public const TAGLINE = 'Sistem Informasi Klinik';
}
