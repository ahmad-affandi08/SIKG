<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure;

use Src\Patient\Contracts\PatientPhone;
use Src\Patient\Contracts\PatientPhoneLookup;
use Src\Patient\Domain\PhoneNumber;
use Src\Patient\Infrastructure\Eloquent\PatientModel;
use Src\Shared\Application\PiiProtector;

final readonly class EloquentPatientPhoneLookup implements PatientPhoneLookup
{
    public function __construct(private PiiProtector $pii) {}

    public function phones(array $patientIds): array
    {
        if ($patientIds === []) {
            return [];
        }
        $out = [];
        foreach (PatientModel::withTrashed()->whereKey(array_values(array_unique($patientIds)))->get() as $p) {
            $phone = $p->phone_encrypted === null ? null : PhoneNumber::tryFrom($this->pii->decrypt($p->phone_encrypted))?->value;
            $out[$p->id] = new PatientPhone($p->id, $p->name, $phone);
        }

        return $out;
    }
}
