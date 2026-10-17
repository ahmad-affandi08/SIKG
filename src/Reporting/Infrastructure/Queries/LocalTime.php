<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Queries;

/**
 * Ekspresi SQL tanggal dan bulan lokal klinik dari kolom UTC. Dibuat sebagai konstanta literal agar tidak ada SQL yang
 * disusun dari string dinamis; selisih zona (contoh `+07:00`) selalu dikirim sebagai binding pertama. Selisih zona
 * dihitung pada awal rentang; zona Indonesia tidak memakai waktu musim panas.
 */
final class LocalTime
{
    public const INVOICE_ISSUED_DAY = "DATE(CONVERT_TZ(i.issued_at, '+00:00', ?))";

    public const INVOICE_ISSUED_MONTH = "DATE_FORMAT(CONVERT_TZ(i.issued_at, '+00:00', ?), '%Y-%m')";

    public const ENCOUNTER_STARTED_DAY = "DATE(CONVERT_TZ(e.started_at, '+00:00', ?))";

    public const ENCOUNTER_STARTED_MONTH = "DATE_FORMAT(CONVERT_TZ(e.started_at, '+00:00', ?), '%Y-%m')";

    public const FIRST_VISIT_DAY = "DATE(CONVERT_TZ(fv.first_at, '+00:00', ?))";

    public const FIRST_VISIT_MONTH = "DATE_FORMAT(CONVERT_TZ(fv.first_at, '+00:00', ?), '%Y-%m')";

    public const APPOINTMENT_START_DAY = "DATE(CONVERT_TZ(a.starts_at, '+00:00', ?))";

    public const APPOINTMENT_START_MONTH = "DATE_FORMAT(CONVERT_TZ(a.starts_at, '+00:00', ?), '%Y-%m')";

    public const STOCK_BATCH_RECEIVED_MONTH = "DATE_FORMAT(CONVERT_TZ(sb.received_at, '+00:00', ?), '%Y-%m')";

    /** Umur tagihan dalam hari: binding hari ini lokal lalu selisih zona. */
    public const INVOICE_AGE_DAYS = "DATEDIFF(?, DATE(CONVERT_TZ(i.issued_at, '+00:00', ?)))";
}
