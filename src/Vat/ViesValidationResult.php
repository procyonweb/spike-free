<?php

namespace Opcodes\Spike\Vat;

use Carbon\CarbonInterface;

class ViesValidationResult
{
    public function __construct(
        public bool $valid,
        public string $countryCode,
        public string $vatNumber,
        public ?string $name = null,
        public ?string $address = null,
        public ?string $requestIdentifier = null,
        public ?CarbonInterface $requestDate = null,
        public bool $serviceAvailable = true,
    ) {}

    public static function serviceUnavailable(string $countryCode, string $vatNumber): static
    {
        return new static(
            valid: false,
            countryCode: $countryCode,
            vatNumber: $vatNumber,
            serviceAvailable: false,
        );
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function isServiceAvailable(): bool
    {
        return $this->serviceAvailable;
    }

    public function getFullVatNumber(): string
    {
        return $this->countryCode . $this->vatNumber;
    }
}
