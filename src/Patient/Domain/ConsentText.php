<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

/**
 * Teks persetujuan pemrosesan data pribadi pasien (UU 27/2022). Setiap perubahan teks menaikkan `VERSION`; versi yang
 * disetujui pasien dicatat bersama waktu dan petugas yang mencatat (PAS-05).
 */
final class ConsentText
{
    public const VERSION = '1';

    public const BODY = 'Saya menyetujui klinik memproses data pribadi dan data kesehatan saya (identitas, riwayat medis, '
        .'pemeriksaan, tindakan, dan tagihan) untuk keperluan pelayanan kesehatan gigi, administrasi, dan kewajiban '
        .'hukum klinik. Data disimpan secara aman dan tidak dibagikan kepada pihak lain tanpa dasar hukum. Saya dapat '
        .'meminta akses, koreksi, atau penjelasan atas data saya kepada klinik.';
}
