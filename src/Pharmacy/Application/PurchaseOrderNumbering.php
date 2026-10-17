<?php

declare(strict_types=1);

namespace Src\Pharmacy\Application;

use Src\Clinic\Contracts\ClinicCalendar;
use Src\Shared\Application\Clock;
use Src\Shared\Application\SequenceGenerator;
use Src\Shared\Application\TenantContext;
use Src\Shared\Domain\NumberPattern;

/**
 * Nomor pesanan pembelian: PO-{tahun}-{urut 5 digit}, urutan mulai dari 1 tiap tahun per klinik. Dipanggil di dalam transaksi
 * pemakainya (ADR-0004).
 */
final readonly class PurchaseOrderNumbering
{
    private const PATTERN = 'PO-{yyyy}-{seq:5}';

    public function __construct(private SequenceGenerator $sequences, private ClinicCalendar $calendar, private TenantContext $tenant, private Clock $clock) {}

    public function next(): string
    {
        $pattern = new NumberPattern(self::PATTERN);
        $now = $this->clock->now();
        $timezone = $this->calendar->timezone();
        $scope = trim('clinic:'.$this->tenant->clinicId().':'.$pattern->scopeKey($now, $timezone), ':');

        return $pattern->render($this->sequences->next('purchase_order_no', $scope), $now, $timezone);
    }
}
