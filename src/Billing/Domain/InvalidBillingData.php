<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

use Src\Shared\Domain\DomainException;

/**
 * Pelanggaran aturan tagihan dan pembayaran. Kode mesin stabil, contoh `invoice.already_paid`, `invoice.overpayment`.
 * Konflik keadaan (tagihan sudah dibayar, dibatalkan, dan sebagainya) dipetakan ke 409.
 */
final class InvalidBillingData extends DomainException
{
    private const CONFLICTS = [
        'invoice.already_paid', 'invoice.already_issued', 'invoice.not_editable', 'invoice.void', 'invoice.not_issued',
        'invoice.overpayment', 'payment.idempotency_conflict', 'invoice.already_void', 'installment.already_exists', 'installment.not_issued',
    ];

    /**
     * @param  non-empty-string  $businessCode
     */
    public function __construct(string $businessCode, string $message)
    {
        parent::__construct($businessCode, $message);
    }

    public function httpStatus(): int
    {
        if (in_array($this->businessCode(), ['invoice.not_found', 'invoice.item_not_found'], true)) {
            return 404;
        }

        return in_array($this->businessCode(), self::CONFLICTS, true) ? 409 : 422;
    }
}
