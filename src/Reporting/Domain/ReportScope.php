<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

/**
 * Seberapa luas laporan yang boleh dilihat pengguna: seluruh klinik, atau hanya yang berkaitan dengan dirinya.
 */
enum ReportScope: string
{
    case Full = 'full';
    case Own = 'own';
}
