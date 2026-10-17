<?php

declare(strict_types=1);

namespace Src\Pharmacy\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Pharmacy\Domain\InvalidPharmacyData;
use Src\Pharmacy\Domain\StockCountPolicy;
use Src\Pharmacy\Domain\StockCountRepository;
use Src\Pharmacy\Domain\StockCountSession;
use Src\Pharmacy\Domain\StockRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\Notification;
use Src\Shared\Application\Notifications\StaffAlerts;
use Src\Shared\Application\Transaction;

/**
 * P2-05: opname terjadwal. Jadwal (interval hari) menerbitkan sesi hitung berisi saldo sistem tiap obat aktif dan
 * memberi tahu pengelola stok; hasil hitung fisik yang diselesaikan menjadi penyesuaian stok lewat StockService (alasan dan
 * audit sama seperti penyesuaian manual). Perintah terjadwal idempoten, aman diulang cron. Izin dicek di Presentation (M9).
 */
final readonly class StockCountService
{
    public function __construct(
        private StockCountRepository $counts,
        private StockRepository $ledger,
        private StockService $stock,
        private StaffAlerts $alerts,
        private ClinicCalendar $calendar,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    /**
     * @return array{schedule: array{enabled: bool, interval_days: int, next_due_date: string|null}, open: array<string, mixed>|null, sessions: list<array<string, mixed>>}
     */
    public function overview(): array
    {
        $open = $this->counts->openSession();

        return [
            'schedule' => $this->counts->schedule(),
            'open' => $open === null ? null : $this->sessionRow($open),
            'sessions' => array_map(fn (StockCountSession $s) => $this->sessionRow($s), $this->counts->recentSessions(30)),
        ];
    }

    /**
     * @return array{session: array<string, mixed>, lines: list<array{id: string, medicine_id: string, name: string, unit: string, system_quantity: int, counted_quantity: int|null, difference: int|null}>}
     */
    public function detail(string $sessionId): array
    {
        $session = $this->counts->findSession($sessionId) ?? throw new InvalidPharmacyData('count.not_found', 'Sesi opname tidak ditemukan.');
        $medicines = [];
        foreach ($this->ledger->overview() as $m) {
            $medicines[$m['id']] = $m;
        }

        $lines = [];
        foreach ($this->counts->lines($sessionId) as $line) {
            $lines[] = [
                'id' => $line->id,
                'medicine_id' => $line->medicineId,
                'name' => $medicines[$line->medicineId]['name'] ?? '-',
                'unit' => $medicines[$line->medicineId]['unit'] ?? '',
                'system_quantity' => $line->systemQuantity,
                'counted_quantity' => $line->countedQuantity,
                'difference' => $line->countedQuantity === null ? null : $line->countedQuantity - $line->systemQuantity,
            ];
        }

        return ['session' => $this->sessionRow($session), 'lines' => $lines];
    }

    public function saveSchedule(string $actorId, bool $enabled, int $intervalDays): void
    {
        if (! StockCountPolicy::validInterval($intervalDays)) {
            throw new InvalidPharmacyData('count.invalid_interval', 'Interval opname antara '.StockCountPolicy::MIN_INTERVAL_DAYS.' dan '.StockCountPolicy::MAX_INTERVAL_DAYS.' hari.');
        }
        $this->tx->run(function () use ($actorId, $enabled, $intervalDays): void {
            $before = $this->counts->schedule();
            $next = $before['next_due_date'];
            if ($enabled && ($next === null || ! $before['enabled'])) {
                // Dihidupkan: sesi pertama jatuh tempo hari ini, selanjutnya mengikuti interval.
                $next = $this->today();
            }
            $this->counts->saveSchedule($actorId, $enabled, $intervalDays, $next);
            $this->audit->record(new AuditEntry($actorId, 'stock.count_schedule_changed', 'stock_count_schedule', 'schedule', $before, ['enabled' => $enabled, 'interval_days' => $intervalDays]));
        });
    }

    public function start(string $actorId, ?string $note): string
    {
        return $this->tx->run(function () use ($actorId, $note): string {
            if ($this->counts->openSession() !== null) {
                throw new InvalidPharmacyData('count.already_open', 'Masih ada sesi opname yang terbuka; selesaikan atau batalkan dulu.');
            }
            $session = $this->counts->createSession($actorId, $this->today(), 'manual', $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 255));
            $this->audit->record(new AuditEntry($actorId, 'stock.count_started', 'stock_count', $session->id, null, ['source' => 'manual']));

            return $session->id;
        });
    }

    /**
     * Dipanggil cron: membuat sesi bila jadwal aktif dan sudah jatuh tempo. Jadwal maju sekali per jatuh tempo sehingga
     * pemanggilan ulang tidak membuat sesi ganda.
     *
     * @return bool true bila sesi baru dibuat
     */
    public function runDue(): bool
    {
        return $this->tx->run(function (): bool {
            $schedule = $this->counts->schedule();
            $today = $this->today();
            if (! $schedule['enabled'] || ! StockCountPolicy::isDue($schedule['next_due_date'], $today)) {
                return false;
            }
            $next = StockCountPolicy::nextDue($today, $schedule['interval_days']);
            $this->counts->saveSchedule(null, true, $schedule['interval_days'], $next);
            if ($this->counts->openSession() !== null) {
                return false;
            }

            $session = $this->counts->createSession(null, $today, 'schedule', null);
            $this->audit->record(new AuditEntry(null, 'stock.count_started', 'stock_count', $session->id, null, ['source' => 'schedule']));
            $this->alerts->raise(new Notification(
                'stock.count_due',
                'Waktunya opname stok',
                'Sesi opname terjadwal sudah dibuka. Hitung stok fisik lalu selesaikan sesinya.',
                '/stock/counts/'.$session->id,
                'stock.manage',
                'stock.count_due:'.$today,
            ));

            return true;
        });
    }

    /**
     * @param  array<string, int|null>  $counted  id baris => jumlah fisik (kosong untuk belum dihitung)
     */
    public function record(string $actorId, string $sessionId, array $counted): void
    {
        foreach ($counted as $quantity) {
            if ($quantity !== null && ($quantity < 0 || $quantity > 1_000_000)) {
                throw new InvalidPharmacyData('stock.invalid_quantity', 'Jumlah fisik harus bilangan bulat tidak negatif.');
            }
        }
        $this->tx->run(function () use ($sessionId, $counted): void {
            $session = $this->counts->lockSession($sessionId) ?? throw new InvalidPharmacyData('count.not_found', 'Sesi opname tidak ditemukan.');
            if (! $session->isOpen()) {
                throw new InvalidPharmacyData('count.closed', 'Sesi opname sudah ditutup.');
            }
            $valid = [];
            foreach ($this->counts->lines($sessionId) as $line) {
                $valid[$line->id] = true;
            }
            foreach ($counted as $lineId => $quantity) {
                if (isset($valid[$lineId])) {
                    $this->counts->recordCount($sessionId, (string) $lineId, $quantity);
                }
            }
        });
    }

    public function complete(string $actorId, string $sessionId): void
    {
        $this->tx->run(function () use ($actorId, $sessionId): void {
            $session = $this->counts->lockSession($sessionId) ?? throw new InvalidPharmacyData('count.not_found', 'Sesi opname tidak ditemukan.');
            if (! $session->isOpen()) {
                throw new InvalidPharmacyData('count.closed', 'Sesi opname sudah ditutup.');
            }

            $adjusted = 0;
            $counted = 0;
            foreach ($this->counts->lines($sessionId) as $line) {
                if ($line->countedQuantity === null) {
                    continue;
                }
                $counted++;
                try {
                    $before = $this->stock->available($line->medicineId);
                    $after = $this->stock->adjust($actorId, $line->medicineId, $line->countedQuantity, 'Opname '.$session->scheduledFor);
                } catch (InvalidPharmacyData $e) {
                    if ($e->businessCode() === 'stock.medicine_not_found') {
                        continue; // obat dinonaktifkan sejak sesi dibuat
                    }
                    throw $e;
                }
                if ($after !== $before) {
                    $adjusted++;
                }
            }
            if ($counted === 0) {
                throw new InvalidPharmacyData('count.nothing_counted', 'Isi jumlah fisik minimal satu obat sebelum menyelesaikan opname.');
            }

            $this->counts->closeSession($actorId, $sessionId, 'completed', $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'stock.count_completed', 'stock_count', $sessionId, null, ['counted' => $counted, 'adjusted' => $adjusted]));
        });
    }

    public function cancel(string $actorId, string $sessionId): void
    {
        $this->tx->run(function () use ($actorId, $sessionId): void {
            $session = $this->counts->lockSession($sessionId) ?? throw new InvalidPharmacyData('count.not_found', 'Sesi opname tidak ditemukan.');
            if (! $session->isOpen()) {
                throw new InvalidPharmacyData('count.closed', 'Sesi opname sudah ditutup.');
            }
            $this->counts->closeSession($actorId, $sessionId, 'cancelled', $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'stock.count_cancelled', 'stock_count', $sessionId, null, null));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionRow(StockCountSession $s): array
    {
        $labels = ['open' => 'Terbuka', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];

        return [
            'id' => $s->id,
            'scheduled_for' => $s->scheduledFor,
            'status' => $s->status,
            'status_label' => $labels[$s->status] ?? $s->status,
            'source' => $s->source,
            'source_label' => $s->source === 'schedule' ? 'Terjadwal' : 'Manual',
            'note' => $s->note,
        ];
    }

    private function today(): string
    {
        return (new DateTimeImmutable('@'.$this->clock->now()->getTimestamp()))->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
    }
}
