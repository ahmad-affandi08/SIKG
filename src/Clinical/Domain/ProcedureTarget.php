<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Sasaran tindakan menurut cakupannya di katalog (RM-05): per gigi, per permukaan, per rahang, atau per kunjungan.
 */
final readonly class ProcedureTarget
{
    /** @var list<Surface> */
    public array $surfaces;

    /**
     * @param  list<Surface>  $surfaces
     */
    private function __construct(public ?Tooth $tooth, array $surfaces, public ?Jaw $jaw)
    {
        $this->surfaces = $surfaces;
    }

    /**
     * @param  'tooth'|'surface'|'jaw'|'visit'  $scope
     * @param  list<string>  $surfaceCodes
     */
    public static function for(string $scope, ?int $toothFdi, array $surfaceCodes, ?string $jaw): self
    {
        $tooth = $toothFdi === null ? null : new Tooth($toothFdi);
        $surfaces = [];
        foreach (array_unique($surfaceCodes) as $code) {
            $surface = Surface::tryFrom($code);
            if ($surface === null || $surface === Surface::Whole) {
                throw new InvalidClinicalData('clinical.invalid_procedure_target', 'Permukaan gigi tidak valid.');
            }
            $surfaces[] = $surface;
        }
        $jawValue = $jaw === null || $jaw === '' ? null : (Jaw::tryFrom($jaw) ?? throw new InvalidClinicalData('clinical.invalid_procedure_target', 'Rahang tidak valid.'));

        $fail = fn (string $message) => throw new InvalidClinicalData('clinical.invalid_procedure_target', $message);

        match ($scope) {
            'tooth' => $tooth === null ? $fail('Tindakan ini wajib menyebut nomor gigi.') : null,
            'surface' => $tooth === null || $surfaces === [] ? $fail('Tindakan ini wajib menyebut nomor gigi dan minimal satu permukaan.') : null,
            'jaw' => $jawValue === null ? $fail('Tindakan ini wajib menyebut rahang.') : null,
            default => null,
        };

        return new self(
            $scope === 'tooth' || $scope === 'surface' ? $tooth : null,
            $scope === 'surface' ? $surfaces : [],
            $scope === 'jaw' ? $jawValue : null,
        );
    }

    public function surfaceCodes(): ?string
    {
        return $this->surfaces === [] ? null : implode('', array_map(fn (Surface $s) => $s->value, $this->surfaces));
    }
}
