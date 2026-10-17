<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use RuntimeException;

/**
 * Galat aturan bisnis dengan kode mesin yang stabil (contoh `appointment.conflict`, `stock.insufficient`).
 * Presentation memetakannya ke HTTP 409 atau 422 dengan format `{ message, code }`.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * @param  non-empty-string  $businessCode  kode bisnis stabil, huruf kecil dengan titik
     */
    public function __construct(private readonly string $businessCode, string $message)
    {
        parent::__construct($message);
    }

    /**
     * @return non-empty-string
     */
    public function businessCode(): string
    {
        return $this->businessCode;
    }

    /**
     * Status HTTP yang disarankan: 409 untuk konflik keadaan, 422 untuk aturan yang dilanggar.
     */
    public function httpStatus(): int
    {
        return 422;
    }
}
