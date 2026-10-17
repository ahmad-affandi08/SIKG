<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Queries;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Reporting\Domain\DateRange;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TenantContext;

/**
 * Satu-satunya pintu baca Reporting ke tabel konteks lain (backend-ddd.md bagian 3). Hanya membaca. Setiap query dimulai
 * dari sini agar selalu terbatas pada klinik dan cabang aktif (M3) dan tidak memuat baris yang dihapus lunak.
 */
final readonly class ReportSource
{
    public function __construct(private TenantContext $tenant, private ClinicCalendar $calendar, private Clock $clock) {}

    /**
     * @param  bool  $soft  tabel punya `deleted_at`; baris yang dihapus lunak tidak dihitung
     * @param  bool  $branch  tabel dibatasi ke cabang pengguna bila pengguna terikat cabang
     */
    public function from(string $table, string $alias, bool $soft = true, bool $branch = true): Builder
    {
        foreach ([$table, $alias] as $identifier) {
            if (preg_match('/^[a-z_]+$/', $identifier) !== 1) {
                throw new InvalidArgumentException('Nama tabel tidak sah.');
            }
        }
        $clinicId = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong; laporan tidak dapat dijalankan.');

        $query = DB::table("{$table} as {$alias}")->where("{$alias}.clinic_id", $clinicId);
        $branchId = $this->tenant->branchId();
        if ($branch && $branchId !== null) {
            $query->where("{$alias}.branch_id", $branchId);
        }
        if ($soft) {
            $query->whereNull("{$alias}.deleted_at");
        }

        return $query;
    }

    public function timezone(): DateTimeZone
    {
        return new DateTimeZone($this->calendar->timezone());
    }

    /** Hari ini menurut kalender klinik (YYYY-MM-DD). */
    public function today(): string
    {
        return $this->clock->now()->setTimezone($this->timezone())->format('Y-m-d');
    }

    /** Waktu sekarang dalam UTC untuk dibandingkan dengan kolom tanggal-waktu. */
    public function nowUtc(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Awal hari lokal klinik sebagai waktu UTC (YYYY-MM-DD HH:MM:SS). */
    public function startOfDay(string $localDate): string
    {
        return (new DateTimeImmutable($localDate.' 00:00:00', $this->timezone()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * Waktu UTC dari basis data menjadi waktu lokal klinik `YYYY-MM-DD HH:MM`.
     */
    public function localTime(?string $utc): ?string
    {
        if ($utc === null || $utc === '') {
            return null;
        }

        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($this->timezone())->format('Y-m-d H:i');
    }

    /**
     * Batas UTC rentang lokal: awal hari pertama (termasuk) dan awal hari setelah hari terakhir (tidak termasuk).
     *
     * @return array{0: string, 1: string}
     */
    public function bounds(DateRange $range): array
    {
        $after = (new DateTimeImmutable($range->to.' 00:00:00', new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');

        return [$this->startOfDay($range->from), $this->startOfDay($after)];
    }

    /**
     * Batasi kolom tanggal-waktu (UTC) ke rentang lokal.
     */
    public function within(Builder $query, string $column, DateRange $range): Builder
    {
        $this->identifier($column);
        [$start, $end] = $this->bounds($range);

        return $query->where($column, '>=', $start)->where($column, '<', $end);
    }

    /**
     * @param  array<mixed>  $ids
     * @return array<string, string> id => nama
     */
    public function names(string $table, array $ids, string $column = 'name'): array
    {
        $this->identifier('t.'.$column);
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => is_string($id) && $id !== '')));
        if ($ids === []) {
            return [];
        }
        $names = [];
        foreach ($this->from($table, 't', false, false)->whereIn('t.id', $ids)->get(['t.id', "t.{$column} as label"]) as $row) {
            $names[(string) $row->id] = (string) $row->label;
        }

        return $names;
    }

    /**
     * Selisih zona klinik terhadap UTC pada awal rentang, contoh `+07:00`, untuk binding ekspresi `LocalTime`.
     */
    public function offset(DateRange $range): string
    {
        $seconds = $this->timezone()->getOffset(new DateTimeImmutable($range->from.' 12:00:00', $this->timezone()));
        $sign = $seconds < 0 ? '-' : '+';
        $abs = abs($seconds);

        return sprintf('%s%02d:%02d', $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60));
    }

    private function identifier(string $column): string
    {
        if (preg_match('/^[a-z_]+\.[a-z_]+$/', $column) !== 1) {
            throw new InvalidArgumentException('Nama kolom tidak sah.');
        }

        return $column;
    }
}
