<?php

namespace Opcodes\Spike\Vat;

use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Traits\ScopedToBillable;

class VatManager
{
    use ScopedToBillable;

    public function __construct(
        protected ViesValidationService $viesService,
        protected TaxDetermination $taxDetermination,
        protected StripeVatSync $stripeSync,
    ) {}

    public function setVatDetails(string $billingCountry, ?string $vatId = null): SpikeBillable|Model
    {
        $billable = $this->getBillable();
        $billingCountry = strtoupper($billingCountry);

        $vatIdType = $vatId ? $this->taxDetermination->getTaxIdTypeForCountry($billingCountry) : null;

        $verified = false;
        $viesResult = null;

        if ($vatId && $this->taxDetermination->isEuCountry($billingCountry)) {
            $viesResult = $this->viesService->validate($billingCountry, $vatId);

            if (! $viesResult->isServiceAvailable()) {
                if (config('spike.vat.block_on_vies_unavailable', false)) {
                    throw VatValidationException::serviceUnavailable();
                }
            } elseif (! $viesResult->isValid()) {
                throw VatValidationException::invalidVatId($billingCountry, $vatId);
            } else {
                $verified = true;
            }
        }

        $treatment = $this->taxDetermination->determineTaxTreatment(
            $billingCountry,
            $vatId,
            $vatIdType,
        );

        if (! $treatment->isValid) {
            throw VatValidationException::vatIdRequired($billingCountry);
        }

        $billable->update([
            'billing_country' => $billingCountry,
            'vat_id' => $vatId,
            'vat_id_type' => $vatIdType,
            'vat_id_verified' => $verified,
            'vat_id_verified_at' => $verified ? now() : null,
            'tax_exempt_status' => $treatment->taxExemptStatus,
            'vies_request_identifier' => $viesResult?->requestIdentifier,
            'vies_company_name' => $viesResult?->name,
            'vies_company_address' => $viesResult?->address,
        ]);

        $this->syncToStripe();

        return $billable->fresh();
    }

    public function validateVatId(): ViesValidationResult
    {
        $billable = $this->getBillable();

        if (! $billable->billing_country || ! $billable->vat_id) {
            return ViesValidationResult::serviceUnavailable('', '');
        }

        return $this->viesService->validate(
            $billable->billing_country,
            $billable->vat_id,
        );
    }

    public function getTaxTreatment(): TaxTreatment
    {
        $billable = $this->getBillable();

        return $this->taxDetermination->determineTaxTreatment(
            $billable->billing_country ?? '',
            $billable->vat_id,
            $billable->vat_id_type,
        );
    }

    public function canPurchase(): bool
    {
        $billable = $this->getBillable();

        if (! $billable->billing_country) {
            return false;
        }

        $treatment = $this->getTaxTreatment();

        return $treatment->isValid;
    }

    public function syncToStripe(): void
    {
        $this->stripeSync->syncToStripe($this->getBillable());
    }
}
