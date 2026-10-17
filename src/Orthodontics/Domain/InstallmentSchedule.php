<?php

declare(strict_types=1);

namespace Src\Orthodontics\Domain;

use DateTimeImmutable;

/**
 * Jadwal cicilan paket ortodonti (P2-04). Bila ada uang muka, cicilan pertama adalah uang muka pada tanggal mulai dan sisanya dibagi
 * rata pada bulan-bulan berikutnya; tanpa uang muka seluruhnya dibagi rata. Sisa pembagian rupiah ditaruh pada cicilan terakhir
 * sehingga jumlah semua cicilan selalu tepat sama dengan biaya paket. Tanggal jatuh tempo bulanan mengikuti tanggal mulai
 * (dipotong ke akhir bulan pendek).
 */
final class InstallmentSchedule
{
    public const MAX_COUNT = 36;

    /**
     * @return list<array{seq: int, due_date: string, amount: int}>
     *
     * @throws InvalidOrthodontics
     */
    public static function generate(int $total, int $count, int $downPayment, DateTimeImmutable $firstDue): array
    {
        if ($total <= 0) {
            throw new InvalidOrthodontics('ortho.fee_invalid', 'Biaya paket harus lebih dari nol.');
        }
        if ($count < 1 || $count > self::MAX_COUNT) {
            throw new InvalidOrthodontics('ortho.installments_invalid', 'Jumlah cicilan harus 1 sampai '.self::MAX_COUNT.'.');
        }
        if ($downPayment < 0 || $downPayment > $total) {
            throw new InvalidOrthodontics('ortho.down_payment_invalid', 'Uang muka tidak boleh negatif atau melebihi biaya paket.');
        }
        if ($downPayment === $total && $count > 1) {
            throw new InvalidOrthodontics('ortho.installments_invalid', 'Uang muka sebesar biaya paket tidak memerlukan cicilan lain.');
        }
        if ($downPayment > 0 && $downPayment < $total && $count < 2) {
            throw new InvalidOrthodontics('ortho.installments_invalid', 'Dengan uang muka, jumlah cicilan minimal 2 (uang muka dan sisanya).');
        }

        $amounts = [];
        if ($downPayment > 0) {
            $amounts[] = $downPayment;
            $rest = $total - $downPayment;
            $parts = $count - 1;
        } else {
            $rest = $total;
            $parts = $count;
        }
        if ($parts > 0) {
            $each = intdiv($rest, $parts);
            for ($i = 1; $i <= $parts; $i++) {
                $amounts[] = $i === $parts ? $rest - $each * ($parts - 1) : $each;
            }
        }
        if (in_array(0, $amounts, true)) {
            throw new InvalidOrthodontics('ortho.installments_invalid', 'Biaya terlalu kecil untuk dibagi menjadi cicilan sebanyak itu.');
        }

        $schedule = [];
        foreach ($amounts as $index => $amount) {
            $schedule[] = ['seq' => $index + 1, 'due_date' => self::monthsAfter($firstDue, $index)->format('Y-m-d'), 'amount' => $amount];
        }

        return $schedule;
    }

    /** Tanggal yang sama `$months` bulan kemudian, dipotong ke hari terakhir bulan itu. */
    public static function monthsAfter(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $first = $date->modify('first day of this month')->modify("+{$months} months");
        $day = min((int) $date->format('j'), (int) $first->format('t'));

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day);
    }
}
