<?php

declare(strict_types=1);

namespace Src\Clinic\Application\Doctors;

use Src\Clinic\Domain\FeeScheme;
use Src\Clinic\Domain\WeeklySchedule;

final readonly class DoctorData
{
    public function __construct(
        public string $name,
        public ?string $sipNo,
        public ?string $specialty,
        public ?string $branchId,
        public ?string $userId,
        public FeeScheme $feeScheme,
        public WeeklySchedule $schedule,
    ) {}
}
