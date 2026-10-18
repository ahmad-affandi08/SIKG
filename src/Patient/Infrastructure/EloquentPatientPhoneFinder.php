<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure;

use Src\Patient\Contracts\PatientPhoneFinder;
use Src\Patient\Contracts\PatientSummary;
use Src\Patient\Domain\PhoneNumber;
use Src\Patient\Infrastructure\Eloquent\PatientModel;
use Src\Shared\Application\PiiProtector;

final readonly class EloquentPatientPhoneFinder implements PatientPhoneFinder
{
    public function __construct(private PiiProtector $pii) {}

    public function normalize(string $input): ?string
    {
        return PhoneNumber::tryFrom($input)?->value;
    }

    public function findByPhone(string $normalizedPhone): array
    {
        $hash = $this->pii->blindIndex('phone', $normalizedPhone);

        return array_values(PatientModel::query()->where('phone_hash', $hash)->orderBy('name')->limit(10)->get()
            ->map(fn (PatientModel $p) => new PatientSummary($p->id, $p->medical_record_no, $p->name, $p->birth_date->toDateString()))->all());
    }
}
