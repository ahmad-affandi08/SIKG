<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure;

use Src\Billing\Application\BillingNumbering;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\InvoiceNumbering;
use Src\Shared\Application\Clock;
use Src\Shared\Application\SequenceGenerator;
use Src\Shared\Application\TenantContext;
use Src\Shared\Domain\NumberPattern;

final readonly class ClinicBillingNumbering implements BillingNumbering
{
    /** Format kwitansi tetap: KW-{tahun}-{urut 5 digit}, urutan mulai dari 1 tiap tahun. */
    private const RECEIPT_PATTERN = 'KW-{yyyy}-{seq:5}';

    public function __construct(
        private InvoiceNumbering $invoices,
        private SequenceGenerator $sequences,
        private ClinicCalendar $calendar,
        private TenantContext $tenant,
        private Clock $clock,
    ) {}

    public function invoiceNumber(): string
    {
        return $this->invoices->next();
    }

    public function receiptNumber(): string
    {
        $pattern = new NumberPattern(self::RECEIPT_PATTERN);
        $now = $this->clock->now();
        $timezone = $this->calendar->timezone();
        $scope = trim('clinic:'.$this->tenant->clinicId().':'.$pattern->scopeKey($now, $timezone), ':');

        return $pattern->render($this->sequences->next('receipt_no', $scope), $now, $timezone);
    }
}
