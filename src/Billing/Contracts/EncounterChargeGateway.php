<?php

declare(strict_types=1);

namespace Src\Billing\Contracts;

use Src\Shared\Domain\DomainException;

/**
 * Kontrak Billing untuk menambahkan satu biaya non-tindakan ke draft tagihan sebuah kunjungan, contoh cicilan paket ortodonti
 * (P2-04). Idempoten per sumber: biaya yang sama tidak masuk dua kali. Salinan jumlah disimpan pada item (M7).
 */
interface EncounterChargeGateway
{
    /**
     * @param  non-empty-string  $sourceType  jenis sumber, maksimal 12 karakter (contoh `ortho`)
     * @return string ID tagihan kunjungan itu
     *
     * @throws DomainException bila tagihan kunjungan sudah terbit
     */
    public function addCharge(string $encounterId, string $patientId, string $sourceType, string $sourceId, string $description, int $amount): string;
}
