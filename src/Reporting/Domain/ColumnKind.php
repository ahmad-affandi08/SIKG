<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

/**
 * Jenis nilai kolom laporan, agar tampilan, PDF, dan Excel memformat sama. Uang adalah bilangan bulat rupiah (M4),
 * persen adalah basis poin (1% = 100), durasi dalam menit; tidak ada bilangan desimal.
 */
enum ColumnKind: string
{
    case Text = 'text';
    case Integer = 'int';
    case Money = 'money';
    case Percent = 'percent';
    case Minutes = 'minutes';
    case Date = 'date';
    case DateTime = 'datetime';
}
