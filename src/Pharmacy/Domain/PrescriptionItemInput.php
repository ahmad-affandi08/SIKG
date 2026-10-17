<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

/**
 * Satu baris resep (OBT-01): obat dari katalog atau teks bebas, dosis, frekuensi, durasi, aturan pakai, dan jumlah.
 * Hanya obat katalog yang dapat dijual di klinik (masuk tagihan dan mengurangi stok, OBT-04).
 */
final readonly class PrescriptionItemInput
{
    public ?string $medicineId;

    public ?string $freeTextName;

    public string $dose;

    public string $frequency;

    public string $duration;

    public ?string $instructions;

    public function __construct(
        ?string $medicineId,
        ?string $freeTextName,
        string $dose,
        string $frequency,
        string $duration,
        ?string $instructions,
        public int $quantity,
        public bool $dispenseInClinic,
    ) {
        $this->medicineId = $medicineId !== null && trim($medicineId) !== '' ? trim($medicineId) : null;
        $this->freeTextName = $freeTextName !== null && trim($freeTextName) !== '' ? trim($freeTextName) : null;
        $this->dose = trim($dose);
        $this->frequency = trim($frequency);
        $this->duration = trim($duration);
        $this->instructions = $instructions !== null && trim($instructions) !== '' ? trim($instructions) : null;

        if (($this->medicineId === null) === ($this->freeTextName === null)) {
            throw new InvalidPharmacyData('prescription.item_source', 'Pilih obat dari katalog atau tulis nama obat, salah satu.');
        }
        if ($this->dispenseInClinic && $this->medicineId === null) {
            throw new InvalidPharmacyData('prescription.dispense_catalog_only', 'Hanya obat dari katalog yang dapat dijual di klinik.');
        }
        if ($this->dose === '' || $this->frequency === '' || $this->duration === '') {
            throw new InvalidPharmacyData('prescription.item_incomplete', 'Dosis, frekuensi, dan durasi wajib diisi.');
        }
        foreach ([$this->dose, $this->frequency, $this->duration] as $text) {
            if (mb_strlen($text) > 100) {
                throw new InvalidPharmacyData('prescription.item_too_long', 'Dosis, frekuensi, dan durasi maksimal 100 karakter.');
            }
        }
        if (($this->freeTextName !== null && mb_strlen($this->freeTextName) > 255) || ($this->instructions !== null && mb_strlen($this->instructions) > 500)) {
            throw new InvalidPharmacyData('prescription.item_too_long', 'Nama obat atau aturan pakai terlalu panjang.');
        }
        if ($quantity < 1 || $quantity > 9999) {
            throw new InvalidPharmacyData('prescription.invalid_quantity', 'Jumlah obat harus antara 1 dan 9999.');
        }
    }
}
