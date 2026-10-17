<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

use Src\Shared\Domain\Money;

/**
 * Aturan jasa dokter yang dibaca dari skema di master dokter (asumsi bentuk di docs/OPEN-QUESTIONS.md, Q-11):
 * `percentage` dengan nilai basis poin dari nilai tindakan, atau `fixed` dengan nilai rupiah per tindakan.
 */
final readonly class DoctorFeeRule
{
    public const PERCENTAGE = 'percentage';

    public const FIXED = 'fixed';

    public function __construct(public string $mode, public int $value) {}

    /**
     * @param  array<mixed>|null  $rule
     */
    public static function fromArray(?array $rule): self
    {
        $mode = is_array($rule) && ($rule['mode'] ?? null) === self::FIXED ? self::FIXED : self::PERCENTAGE;
        $value = is_array($rule) && isset($rule['value']) && is_numeric($rule['value']) ? max(0, (int) $rule['value']) : 0;

        return new self($mode, $value);
    }

    /**
     * Aturan untuk satu kode tindakan dari skema `{default, overrides}`; tanpa skema berarti tanpa jasa.
     *
     * @param  array<mixed>|null  $scheme
     */
    public static function forProcedure(?array $scheme, string $procedureCode): self
    {
        if ($scheme === null) {
            return new self(self::PERCENTAGE, 0);
        }
        $overrides = isset($scheme['overrides']) && is_array($scheme['overrides']) ? $scheme['overrides'] : [];
        if (isset($overrides[$procedureCode]) && is_array($overrides[$procedureCode])) {
            return self::fromArray($overrides[$procedureCode]);
        }

        return self::fromArray(isset($scheme['default']) && is_array($scheme['default']) ? $scheme['default'] : null);
    }

    /**
     * @param  int  $amount  nilai tindakan setelah diskon item (rupiah)
     * @param  int  $quantity  jumlah tindakan
     */
    public function feeFor(int $amount, int $quantity): Money
    {
        if ($this->mode === self::FIXED) {
            return Money::rupiah($this->value)->multiply(max(0, $quantity));
        }

        return Money::rupiah(max(0, $amount))->percent($this->value);
    }

    /** Teks aturan untuk laporan, contoh "40%" atau "Rp 50.000 per tindakan". */
    public function label(): string
    {
        if ($this->mode === self::FIXED) {
            return 'Rp '.number_format($this->value, 0, ',', '.').' per tindakan';
        }
        $whole = intdiv($this->value, 100);
        $frac = $this->value % 100;

        return $frac === 0 ? $whole.'%' : $whole.','.rtrim(sprintf('%02d', $frac), '0').'%';
    }
}
