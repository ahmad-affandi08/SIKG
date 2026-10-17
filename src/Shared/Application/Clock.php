<?php

declare(strict_types=1);

namespace Src\Shared\Application;

use DateTimeImmutable;

/**
 * Sumber waktu. Domain tidak memanggil now(); Handler mengambil waktu dari sini dan meneruskannya.
 * Selalu UTC (ditampilkan sesuai zona waktu klinik di Presentation).
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
