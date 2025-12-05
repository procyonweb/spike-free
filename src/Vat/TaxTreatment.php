<?php

namespace Opcodes\Spike\Vat;

class TaxTreatment
{
    public function __construct(
        public ?string $taxExemptStatus,
        public string $reason,
        public bool $requiresVatId = false,
        public bool $isValid = true,
    ) {}

    public function shouldChargeVat(): bool
    {
        return $this->taxExemptStatus === 'none';
    }

    public function isReverseCharge(): bool
    {
        return $this->taxExemptStatus === 'reverse';
    }

    public function isExempt(): bool
    {
        return $this->taxExemptStatus === 'exempt';
    }

    public function getStripeExemptValue(): string
    {
        return $this->taxExemptStatus ?? 'none';
    }
}
