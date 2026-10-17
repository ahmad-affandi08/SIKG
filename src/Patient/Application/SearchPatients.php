<?php

declare(strict_types=1);

namespace Src\Patient\Application;

use Src\Patient\Domain\PatientRepository;
use Src\Patient\Domain\PatientSummary;

/**
 * PAS-02: pencarian pasien berdasarkan nama, nomor RM, NIK, atau nomor HP. Maksimal 20 hasil.
 */
final readonly class SearchPatients
{
    public const LIMIT = 20;

    public function __construct(private PatientRepository $patients) {}

    /**
     * @return list<PatientSummary>
     */
    public function __invoke(string $term): array
    {
        $term = trim($term);

        return $term === '' ? [] : $this->patients->search($term, self::LIMIT);
    }
}
