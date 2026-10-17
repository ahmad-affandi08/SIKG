<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

/**
 * Skema jasa dokter (MST-02, P2-09): aturan bawaan dan pengecualian per kode tindakan, ditambah jasa bertingkat atas produksi
 * bulanan (tindakan tanpa pengecualian) dan beban biaya lab dokter. Disimpan di master dokter; perhitungannya di laporan jasa
 * dan slip (asumsi di docs/OPEN-QUESTIONS.md, Q-11 dan Q-28).
 */
final readonly class FeeScheme
{
    public const MAX_TIERS = 6;

    /**
     * @param  array<string, FeeRule>  $overrides  kode tindakan => aturan
     * @param  list<FeeTier>  $tiers  kosong berarti tanpa jasa bertingkat; batas menaik dan tingkat terakhir tanpa batas
     * @param  int  $labShareBasisPoints  bagian biaya lab pesanan dokter yang dibebankan ke jasanya (10000 = 100%)
     */
    public function __construct(public FeeRule $default, public array $overrides = [], public array $tiers = [], public int $labShareBasisPoints = 0)
    {
        if ($labShareBasisPoints < 0 || $labShareBasisPoints > 10_000) {
            throw new InvalidDoctorData('doctor.invalid_fee', 'Beban biaya lab harus antara 0 dan 100 persen.');
        }
        if ($tiers !== []) {
            if (count($tiers) > self::MAX_TIERS) {
                throw new InvalidDoctorData('doctor.invalid_fee', 'Jasa bertingkat paling banyak '.self::MAX_TIERS.' tingkat.');
            }
            $previous = 0;
            foreach ($tiers as $index => $tier) {
                $last = $index === count($tiers) - 1;
                if ($last !== ($tier->upTo === null)) {
                    throw new InvalidDoctorData('doctor.invalid_fee', 'Hanya tingkat terakhir yang tanpa batas, dan tingkat terakhir harus tanpa batas.');
                }
                if ($tier->upTo !== null) {
                    if ($tier->upTo <= $previous) {
                        throw new InvalidDoctorData('doctor.invalid_fee', 'Batas tingkat jasa harus makin besar.');
                    }
                    $previous = $tier->upTo;
                }
            }
        }
    }

    public static function none(): self
    {
        return new self(new FeeRule(FeeMode::Percentage, 0));
    }

    public function ruleFor(string $procedureCode): FeeRule
    {
        return $this->overrides[$procedureCode] ?? $this->default;
    }

    /**
     * Kunci `tiers` dan `lab_share_bp` hanya ada bila dipakai, sehingga skema lama tidak berubah bentuk.
     *
     * @return array{default: array{mode: string, value: int}, overrides: array<string, array{mode: string, value: int}>, tiers?: list<array{up_to: int|null, rate_bp: int}>, lab_share_bp?: int}
     */
    public function toArray(): array
    {
        $data = [
            'default' => $this->default->toArray(),
            'overrides' => array_map(fn (FeeRule $r) => $r->toArray(), $this->overrides),
        ];
        if ($this->tiers !== []) {
            $data['tiers'] = array_map(fn (FeeTier $t) => $t->toArray(), $this->tiers);
        }
        if ($this->labShareBasisPoints > 0) {
            $data['lab_share_bp'] = $this->labShareBasisPoints;
        }

        return $data;
    }

    /**
     * @param  array<mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        if ($data === null || ! isset($data['default']) || ! is_array($data['default'])) {
            return self::none();
        }

        $overrides = [];
        $raw = isset($data['overrides']) && is_array($data['overrides']) ? $data['overrides'] : [];
        foreach ($raw as $code => $rule) {
            if (is_array($rule)) {
                $overrides[(string) $code] = FeeRule::fromArray($rule);
            }
        }

        $tiers = [];
        $rawTiers = isset($data['tiers']) && is_array($data['tiers']) ? $data['tiers'] : [];
        foreach ($rawTiers as $tier) {
            if (is_array($tier)) {
                $tiers[] = FeeTier::fromArray($tier);
            }
        }
        $share = isset($data['lab_share_bp']) && is_int($data['lab_share_bp']) ? $data['lab_share_bp'] : 0;

        return new self(FeeRule::fromArray($data['default']), $overrides, $tiers, $share);
    }
}
