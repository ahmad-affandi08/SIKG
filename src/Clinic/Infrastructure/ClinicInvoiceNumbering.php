<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\InvoiceNumbering;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Shared\Application\Clock;
use Src\Shared\Application\SequenceGenerator;

final readonly class ClinicInvoiceNumbering implements InvoiceNumbering
{
    public function __construct(private SequenceGenerator $sequences, private Clock $clock) {}

    public function next(): string
    {
        $clinic = ClinicModel::query()->firstOrFail();
        $pattern = $clinic->clinicSettings()->invoicePattern;
        $now = $this->clock->now();
        $scope = trim("clinic:{$clinic->id}:".$pattern->scopeKey($now, $clinic->timezone), ':');

        return $pattern->render($this->sequences->next('invoice_no', $scope), $now, $clinic->timezone);
    }
}
