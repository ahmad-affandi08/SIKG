<?php

declare(strict_types=1);

namespace Src\Pharmacy\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Pharmacy\Domain\StockAlertPolicy;
use Src\Pharmacy\Domain\StockRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\Notification;
use Src\Shared\Application\Notifications\StaffAlerts;

/**
 * OBT-06: peringatan stok di bawah minimum dan batch yang kedaluwarsa dalam 30 hari. Dua keluaran: daftar untuk halaman stok
 * dan pemberitahuan harian untuk staf (SYS-05) yang terbit sekali per hari dan jenis, sehingga aman dijalankan berulang oleh cron.
 */
final readonly class StockAlerts
{
    private const LIST_IN_MESSAGE = 5;

    public function __construct(
        private StockRepository $stock,
        private ClinicCalendar $calendar,
        private StaffAlerts $alerts,
        private Clock $clock,
    ) {}

    /**
     * @return array{
     *     low: list<array{id: string, name: string, unit: string, quantity: int, min_stock: int}>,
     *     expiring: list<array{medicine_id: string, name: string, unit: string, batch_no: string|null, expiry_date: string, days_left: int, received: int, quantity: int}>,
     *     window_days: int
     * }
     */
    public function overview(): array
    {
        $today = $this->today();

        $low = [];
        foreach ($this->stock->overview() as $row) {
            if ($row['is_active'] && StockAlertPolicy::isLow($row['quantity'], $row['min_stock'])) {
                $low[] = ['id' => $row['id'], 'name' => $row['name'], 'unit' => $row['unit'], 'quantity' => $row['quantity'], 'min_stock' => $row['min_stock']];
            }
        }

        $expiring = [];
        foreach ($this->stock->expiringBatches(StockAlertPolicy::expiryLimit($today)) as $batch) {
            $expiring[] = [...$batch, 'days_left' => StockAlertPolicy::daysLeft($today, $batch['expiry_date'])];
        }

        return ['low' => $low, 'expiring' => $expiring, 'window_days' => StockAlertPolicy::EXPIRY_WINDOW_DAYS];
    }

    /**
     * Menerbitkan pemberitahuan hari ini bila ada yang perlu diperingatkan dan jam lokal sudah lewat batas awal.
     *
     * @return array{low: bool, expiring: bool}
     */
    public function raiseDaily(): array
    {
        $local = $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()));
        if ((int) $local->format('G') < StockAlertPolicy::ALERT_FROM_HOUR) {
            return ['low' => false, 'expiring' => false];
        }
        $day = $local->format('Y-m-d');
        $overview = $this->overview();

        $low = false;
        if ($overview['low'] !== []) {
            $items = array_map(fn (array $m) => "{$m['name']} ({$m['quantity']} dari minimum {$m['min_stock']} {$m['unit']})", $overview['low']);
            $low = $this->alerts->raise(new Notification(
                'stock.low',
                'Stok obat menipis',
                count($items).' obat di bawah stok minimum: '.$this->summarize($items).'.',
                '/stock',
                'stock.manage',
                'stock.low:'.$day,
            ));
        }

        $expiring = false;
        if ($overview['expiring'] !== []) {
            $items = array_map(function (array $b): string {
                $when = $b['days_left'] < 0 ? 'sudah kedaluwarsa' : "{$b['days_left']} hari lagi";

                return $b['name'].($b['batch_no'] === null ? '' : " batch {$b['batch_no']}").' ('.$when.')';
            }, $overview['expiring']);
            $expiring = $this->alerts->raise(new Notification(
                'stock.expiring',
                'Obat hampir kedaluwarsa',
                count($items).' batch kedaluwarsa dalam '.StockAlertPolicy::EXPIRY_WINDOW_DAYS.' hari atau sudah lewat: '.$this->summarize($items).'.',
                '/stock',
                'stock.manage',
                'stock.expiring:'.$day,
            ));
        }

        return ['low' => $low, 'expiring' => $expiring];
    }

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d'));
    }

    /**
     * @param  list<string>  $items
     */
    private function summarize(array $items): string
    {
        $shown = array_slice($items, 0, self::LIST_IN_MESSAGE);
        $rest = count($items) - count($shown);

        return implode('; ', $shown).($rest > 0 ? "; dan {$rest} lainnya" : '');
    }
}
