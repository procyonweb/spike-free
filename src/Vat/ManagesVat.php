<?php

namespace Opcodes\Spike\Vat;

trait ManagesVat
{
    public function vatManager(): VatManager
    {
        return app(VatManager::class)->billable($this);
    }

    public function setVatDetails(string $billingCountry, ?string $vatId = null): static
    {
        $this->vatManager()->setVatDetails($billingCountry, $vatId);

        return $this;
    }

    public function validateVatId(): ViesValidationResult
    {
        return $this->vatManager()->validateVatId();
    }

    public function getTaxTreatment(): TaxTreatment
    {
        return $this->vatManager()->getTaxTreatment();
    }

    public function canPurchase(): bool
    {
        return $this->vatManager()->canPurchase();
    }

    public function syncVatToStripe(): void
    {
        $this->vatManager()->syncToStripe();
    }

    public function stripeAddress(): array
    {
        if ($this->billing_country) {
            return [
                'country' => $this->billing_country,
            ];
        }

        return [];
    }
}
