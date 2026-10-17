<?php

declare(strict_types=1);

namespace Src\Clinic\Application\Profile;

final readonly class ClinicProfile
{
    public function __construct(
        public string $name,
        public ?string $address,
        public ?string $city,
        public ?string $licenseNo,
        public ?string $receiptHeader,
        public ?string $prescriptionHeader,
        public bool $hasLogo = false,
    ) {}

    /**
     * @return array{name: string, address: ?string, city: ?string, license_no: ?string, receipt_header: ?string, prescription_header: ?string, has_logo: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'license_no' => $this->licenseNo,
            'receipt_header' => $this->receiptHeader,
            'prescription_header' => $this->prescriptionHeader,
            'has_logo' => $this->hasLogo,
        ];
    }
}
