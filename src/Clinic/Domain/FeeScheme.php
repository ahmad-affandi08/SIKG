<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

/**
 * Skema jasa dokter (MST-02): aturan bawaan dan pengecualian per kode tindakan. Hanya disimpan; perhitungan jasa
 * di laporan masuk P2-09 (asumsi di docs/OPEN-QUESTIONS.md, Q-11).
 */
final readonly class FeeScheme
{
    /**
     * @param  array<string, FeeRule>  $overrides  kode tindakan => aturan
     */
    public function __construct(public FeeRule $default, public array $overrides = []) {}

    public static function none(): self
    {
        return new self(new FeeRule(FeeMode::Percentage, 0));
    }

    public function ruleFor(string $procedureCode): FeeRule
    {
        return $this->overrides[$procedureCode] ?? $this->default;
    }

    /**
     * @return array{default: array{mode: string, value: int}, overrides: array<string, array{mode: string, value: int}>}
     */
    public function toArray(): array
    {
        return [
            'default' => $this->default->toArray(),
            'overrides' => array_map(fn (FeeRule $r) => $r->toArray(), $this->overrides),
        ];
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

        return new self(FeeRule::fromArray($data['default']), $overrides);
    }
}
