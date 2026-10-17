<?php

declare(strict_types=1);

namespace Src\Billing\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Domain\InvalidBillingData;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * KSR-08: tutup kas harian. Kasir mengisi uang tunai fisik; sistem menghitung selisih terhadap catatan penerimaan tunai
 * kasir itu sendiri yang belum pernah ditutup. Penutupan bersifat final dan tercatat di audit; pembayaran yang tercakup
 * tidak ikut penutupan lain.
 */
final readonly class CashClosingService
{
    public function __construct(
        private CashRegister $register,
        private ClinicCalendar $calendar,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    /**
     * @return array{date: string, system_amount: int, payments: int}
     */
    public function preview(string $cashierId, ?string $date = null): array
    {
        $date = $this->resolveDate($date);
        $unclosed = $this->register->unclosed($cashierId, $this->cutoff($date));

        return ['date' => $date, 'system_amount' => $unclosed['amount'], 'payments' => $unclosed['payments']];
    }

    /**
     * @return array{id: string, date: string, system_amount: int, counted_amount: int, difference_amount: int}
     */
    public function close(string $actorId, int $countedAmount, ?string $note, ?string $date = null): array
    {
        if ($countedAmount < 0) {
            throw new InvalidBillingData('cash_closing.invalid_amount', 'Uang fisik tidak boleh negatif.');
        }
        $date = $this->resolveDate($date);
        $note = $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 255);

        return $this->tx->run(function () use ($actorId, $countedAmount, $note, $date): array {
            $before = $this->cutoff($date);
            $unclosed = $this->register->unclosed($actorId, $before);
            $difference = $countedAmount - $unclosed['amount'];
            if ($difference !== 0 && $note === null) {
                throw new InvalidBillingData('cash_closing.note_required', 'Ada selisih kas; isi catatan penjelasannya.');
            }
            $id = $this->register->close($actorId, $actorId, $date, $unclosed['amount'], $countedAmount, $difference, $note, $this->clock->now(), $before);
            $this->audit->record(new AuditEntry($actorId, 'cash.closed', 'cash_closing', $id, null, [
                'date' => $date,
                'system_amount' => $unclosed['amount'],
                'counted_amount' => $countedAmount,
                'difference_amount' => $difference,
                'payments' => $unclosed['payments'],
            ]));

            return ['id' => $id, 'date' => $date, 'system_amount' => $unclosed['amount'], 'counted_amount' => $countedAmount, 'difference_amount' => $difference];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(?string $cashierId, int $limit = 30): array
    {
        return $this->register->history($cashierId, $limit);
    }

    private function resolveDate(?string $date): string
    {
        $timezone = new DateTimeZone($this->calendar->timezone());
        $today = $this->clock->now()->setTimezone($timezone)->format('Y-m-d');
        if ($date === null || $date === '') {
            return $today;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || $date > $today) {
            throw new InvalidBillingData('cash_closing.invalid_date', 'Tanggal tutup kas tidak valid atau di masa depan.');
        }

        return $date;
    }

    /**
     * Batas atas pembayaran yang tercakup: akhir hari yang ditutup di zona klinik, tetapi tidak melewati saat ini.
     */
    private function cutoff(string $date): DateTimeImmutable
    {
        $endOfDay = (new DateTimeImmutable($date.' 00:00:00', new DateTimeZone($this->calendar->timezone())))->modify('+1 day')->setTimezone(new DateTimeZone('UTC'));
        $now = $this->clock->now();

        return $endOfDay < $now ? $endOfDay : $now->modify('+1 second');
    }
}
