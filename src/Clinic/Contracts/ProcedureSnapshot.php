<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Salinan data tindakan pada saat dipilih. Konteks lain menyimpan salinan ini (kode, nama, tarif) pada item miliknya,
 * sehingga perubahan tarif di katalog tidak mengubah data lama (MST-08, M7).
 */
final readonly class ProcedureSnapshot
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $scope,
        public int $priceAmount,
    ) {}

    /**
     * @return array{id: string, code: string, name: string, scope: string, price_amount: int}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'scope' => $this->scope, 'price_amount' => $this->priceAmount];
    }
}
