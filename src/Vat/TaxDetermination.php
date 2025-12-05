<?php

namespace Opcodes\Spike\Vat;

class TaxDetermination
{
    protected const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    public function __construct(
        protected ViesValidationService $viesService,
    ) {}

    public function determineTaxTreatment(
        string $billingCountry,
        ?string $vatId = null,
        ?string $vatIdType = null,
    ): TaxTreatment {
        $billingCountry = strtoupper($billingCountry);
        $sellerCountry = strtoupper(config('spike.vat.seller_country', 'LU'));

        if ($billingCountry === $sellerCountry) {
            return new TaxTreatment(
                taxExemptStatus: 'none',
                reason: 'domestic_sale',
                requiresVatId: true,
            );
        }

        if ($this->isEuCountry($billingCountry)) {
            if ($vatId && $this->isVatIdValid($billingCountry, $vatId)) {
                return new TaxTreatment(
                    taxExemptStatus: 'reverse',
                    reason: 'eu_reverse_charge',
                    requiresVatId: true,
                );
            }

            return new TaxTreatment(
                taxExemptStatus: null,
                reason: 'eu_vat_id_required',
                requiresVatId: true,
                isValid: false,
            );
        }

        return new TaxTreatment(
            taxExemptStatus: 'exempt',
            reason: 'non_eu_export',
            requiresVatId: true,
        );
    }

    public function isEuCountry(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), self::EU_COUNTRIES);
    }

    public function isVatIdValid(string $countryCode, string $vatId): bool
    {
        $result = $this->viesService->validate($countryCode, $vatId);

        return $result->isValid();
    }

    public function getSellerCountry(): string
    {
        return strtoupper(config('spike.vat.seller_country', 'LU'));
    }

    public function getTaxIdTypeForCountry(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        if ($this->isEuCountry($countryCode)) {
            return 'eu_vat';
        }

        return match ($countryCode) {
            'GB' => 'gb_vat',
            'NO' => 'no_vat',
            'CH' => 'ch_vat',
            'US' => 'us_ein',
            'CA' => 'ca_bn',
            'AU' => 'au_abn',
            'NZ' => 'nz_gst',
            'SG' => 'sg_uen',
            'JP' => 'jp_cn',
            default => 'eu_vat',
        };
    }
}
