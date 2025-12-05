<?php

namespace Opcodes\Spike\Vat;

use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\Contracts\SpikeBillable;

class StripeVatSync
{
    public function __construct(
        protected TaxDetermination $taxDetermination,
    ) {}

    public function syncToStripe(SpikeBillable|Model $billable): void
    {
        if (! method_exists($billable, 'hasStripeId') || ! $billable->hasStripeId()) {
            return;
        }

        if (! $billable->billing_country) {
            return;
        }

        $treatment = $this->taxDetermination->determineTaxTreatment(
            $billable->billing_country,
            $billable->vat_id,
            $billable->vat_id_type,
        );

        $billable->updateStripeCustomer([
            'tax_exempt' => $treatment->getStripeExemptValue(),
            'address' => [
                'country' => $billable->billing_country,
            ],
        ]);

        if ($billable->vat_id && $billable->vat_id_verified) {
            $this->syncTaxIdToStripe($billable);
        }
    }

    protected function syncTaxIdToStripe(SpikeBillable|Model $billable): void
    {
        $existingTaxIds = $billable->taxIds();

        $taxIdType = $billable->vat_id_type ??
            $this->taxDetermination->getTaxIdTypeForCountry($billable->billing_country);

        foreach ($existingTaxIds as $taxId) {
            if ($taxId->value === $billable->vat_id && $taxId->type === $taxIdType) {
                return;
            }
        }

        foreach ($existingTaxIds as $taxId) {
            if ($taxId->type === $taxIdType) {
                $billable->deleteTaxId($taxId->id);
            }
        }

        $billable->createTaxId($taxIdType, $billable->vat_id);
    }

    public function getCustomerCreationOptions(SpikeBillable|Model $billable): array
    {
        $options = [];

        if ($billable->billing_country) {
            $treatment = $this->taxDetermination->determineTaxTreatment(
                $billable->billing_country,
                $billable->vat_id,
                $billable->vat_id_type,
            );

            $options['tax_exempt'] = $treatment->getStripeExemptValue();
            $options['address'] = [
                'country' => $billable->billing_country,
            ];
        }

        return $options;
    }
}
