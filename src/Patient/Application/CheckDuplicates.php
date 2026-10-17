<?php

declare(strict_types=1);

namespace Src\Patient\Application;

use Src\Patient\Domain\DuplicateCandidate;
use Src\Patient\Domain\PatientRepository;
use Src\Shared\Application\Clock;

/**
 * PAS-03: pemeriksaan duplikat sebelum menyimpan (formulir memanggilnya saat NIK, nama, tanggal lahir, atau HP terisi).
 */
final readonly class CheckDuplicates
{
    public function __construct(private PatientRepository $patients, private Clock $clock) {}

    /**
     * @return list<DuplicateCandidate>
     */
    public function __invoke(PatientDraft $draft, ?string $ignoreId = null): array
    {
        return $this->patients->findDuplicates($draft->toProfile($this->clock->now()), $ignoreId);
    }
}
