<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\MedicalRecordNumbering;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Shared\Application\Clock;
use Src\Shared\Application\SequenceGenerator;

final readonly class ClinicMedicalRecordNumbering implements MedicalRecordNumbering
{
    public function __construct(private SequenceGenerator $sequences, private Clock $clock) {}

    public function next(): string
    {
        $clinic = ClinicModel::query()->firstOrFail();
        $pattern = $clinic->clinicSettings()->medicalRecordPattern;
        $now = $this->clock->now();
        $scope = trim("clinic:{$clinic->id}:".$pattern->scopeKey($now, $clinic->timezone), ':');

        return $pattern->render($this->sequences->next('medical_record_no', $scope), $now, $clinic->timezone);
    }
}
