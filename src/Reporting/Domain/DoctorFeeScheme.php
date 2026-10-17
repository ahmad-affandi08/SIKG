<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

/**
 * Skema jasa dokter yang dibaca dari master dokter untuk perhitungan laporan dan slip (P2-09; bentuk JSON di Q-11 dan Q-28):
 * - `overrides` per kode tindakan (persentase atau jumlah tetap) selalu dipakai untuk tindakan itu;
 * - tindakan lain memakai `tiers` bila ada (persentase marginal atas produksi bulanan tindakan tersebut), selain itu `default`;
 * - `lab_share_bp` adalah bagian biaya lab pesanan dokter itu (yang diterima pada bulan tersebut) yang dipotong dari jasanya.
 * Semua hitungan bilangan bulat rupiah, dibulatkan ke bawah per tingkat (M4).
 */
final readonly class DoctorFeeScheme
{
    /**
     * @param  array<string, DoctorFeeRule>  $overrides
     * @param  list<array{up_to: int|null, rate_bp: int}>  $tiers
     */
    private function __construct(private DoctorFeeRule $default, private array $overrides, private array $tiers, private int $labShareBp) {}

    /**
     * @param  array<mixed>|null  $scheme
     */
    public static function fromArray(?array $scheme): self
    {
        if ($scheme === null) {
            return new self(new DoctorFeeRule(DoctorFeeRule::PERCENTAGE, 0), [], [], 0);
        }
        $overrides = [];
        foreach (isset($scheme['overrides']) && is_array($scheme['overrides']) ? $scheme['overrides'] : [] as $code => $rule) {
            if (is_array($rule)) {
                $overrides[(string) $code] = DoctorFeeRule::fromArray($rule);
            }
        }
        $tiers = [];
        foreach (isset($scheme['tiers']) && is_array($scheme['tiers']) ? $scheme['tiers'] : [] as $tier) {
            if (is_array($tier) && isset($tier['rate_bp']) && is_numeric($tier['rate_bp'])) {
                $upTo = isset($tier['up_to']) && is_numeric($tier['up_to']) ? (int) $tier['up_to'] : null;
                $tiers[] = ['up_to' => $upTo, 'rate_bp' => max(0, min(10_000, (int) $tier['rate_bp']))];
            }
        }
        $share = isset($scheme['lab_share_bp']) && is_numeric($scheme['lab_share_bp']) ? max(0, min(10_000, (int) $scheme['lab_share_bp'])) : 0;

        return new self(DoctorFeeRule::fromArray(isset($scheme['default']) && is_array($scheme['default']) ? $scheme['default'] : null), $overrides, $tiers, $share);
    }

    public function hasTiers(): bool
    {
        return $this->tiers !== [];
    }

    public function labShareBasisPoints(): int
    {
        return $this->labShareBp;
    }

    /**
     * Jasa satu bulan.
     *
     * @param  list<array{code: string, name: string, qty: int, base: int}>  $procedures  tindakan pada tagihan final bulan itu
     * @param  int  $labCostAmount  biaya lab pesanan dokter yang diterima pada bulan itu
     */
    public function month(string $month, array $procedures, int $labCostAmount): MonthlyFee
    {
        $lines = [];
        $tiered = [];
        foreach ($procedures as $p) {
            if (isset($this->overrides[$p['code']]) || ! $this->hasTiers()) {
                $rule = $this->overrides[$p['code']] ?? $this->default;
                $lines[] = new FeeLine($p['code'], $p['name'], $p['qty'], $p['base'], $rule->label(), $rule->feeFor($p['base'], $p['qty'])->amount);
            } else {
                $tiered[] = $p;
            }
        }
        if ($tiered !== []) {
            $total = array_sum(array_column($tiered, 'base'));
            $fee = $this->marginal($total);
            $assigned = 0;
            foreach ($tiered as $index => $p) {
                $part = $index === count($tiered) - 1 ? $fee - $assigned : ($total > 0 ? intdiv($fee * $p['base'], $total) : 0);
                $assigned += $part;
                $lines[] = new FeeLine($p['code'], $p['name'], $p['qty'], $p['base'], 'Bertingkat', $part);
            }
        }

        $base = array_sum(array_column($procedures, 'base'));
        $gross = array_sum(array_map(fn (FeeLine $l) => $l->fee, $lines));
        $deduction = intdiv($labCostAmount * $this->labShareBp, 10_000);
        $net = max(0, $gross - $deduction);

        return new MonthlyFee($month, $lines, $base, $gross, $labCostAmount, $deduction, $net, max(0, $deduction - $gross));
    }

    /** Jasa marginal: tiap tingkat berlaku untuk bagian produksi di dalam batasnya. */
    private function marginal(int $production): int
    {
        $fee = 0;
        $lower = 0;
        foreach ($this->tiers as $tier) {
            $upper = $tier['up_to'] ?? PHP_INT_MAX;
            $portion = max(0, min($production, $upper) - $lower);
            $fee += intdiv($portion * $tier['rate_bp'], 10_000);
            $lower = $upper;
            if ($production <= $upper) {
                break;
            }
        }

        return $fee;
    }

    /** Ringkasan skema untuk catatan laporan dan slip. */
    public function describe(): string
    {
        $parts = [];
        if ($this->hasTiers()) {
            $lower = 0;
            $tiers = [];
            foreach ($this->tiers as $t) {
                $rate = (new DoctorFeeRule(DoctorFeeRule::PERCENTAGE, $t['rate_bp']))->label();
                $tiers[] = $t['up_to'] === null
                    ? 'di atas Rp '.number_format($lower, 0, ',', '.').' = '.$rate
                    : 'Rp '.number_format($lower, 0, ',', '.').' sampai Rp '.number_format($t['up_to'], 0, ',', '.').' = '.$rate;
                $lower = $t['up_to'] ?? $lower;
            }
            $parts[] = 'Bertingkat per bulan: '.implode('; ', $tiers);
        } else {
            $parts[] = 'Aturan bawaan '.$this->default->label();
        }
        if ($this->overrides !== []) {
            $parts[] = 'pengecualian '.count($this->overrides).' kode tindakan';
        }
        if ($this->labShareBp > 0) {
            $parts[] = 'beban biaya lab '.(new DoctorFeeRule(DoctorFeeRule::PERCENTAGE, $this->labShareBp))->label();
        }

        return implode('; ', $parts);
    }
}
