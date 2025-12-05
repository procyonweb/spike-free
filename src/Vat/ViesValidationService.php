<?php

namespace Opcodes\Spike\Vat;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

class ViesValidationService
{
    protected const VIES_WSDL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    public function validate(string $countryCode, string $vatNumber): ViesValidationResult
    {
        $countryCode = strtoupper($countryCode);
        $vatNumber = $this->sanitizeVatNumber($vatNumber, $countryCode);

        $cacheKey = "vies_validation:{$countryCode}:{$vatNumber}";
        $cacheTtl = config('spike.vat.vies_cache_ttl', 86400);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($countryCode, $vatNumber) {
            return $this->performValidation($countryCode, $vatNumber);
        });
    }

    protected function performValidation(string $countryCode, string $vatNumber): ViesValidationResult
    {
        try {
            $client = new SoapClient(self::VIES_WSDL, [
                'trace' => true,
                'exceptions' => true,
                'connection_timeout' => 10,
            ]);

            $sellerCountry = config('spike.vat.seller_country');
            $sellerVatId = config('spike.vat.seller_vat_id');

            if ($sellerCountry && $sellerVatId) {
                $sellerVatNumber = $this->sanitizeVatNumber($sellerVatId, $sellerCountry);

                $response = $client->checkVatApprox([
                    'countryCode' => $countryCode,
                    'vatNumber' => $vatNumber,
                    'requesterCountryCode' => strtoupper($sellerCountry),
                    'requesterVatNumber' => $sellerVatNumber,
                ]);
            } else {
                $response = $client->checkVat([
                    'countryCode' => $countryCode,
                    'vatNumber' => $vatNumber,
                ]);
            }

            return new ViesValidationResult(
                valid: $response->valid,
                countryCode: $response->countryCode,
                vatNumber: $response->vatNumber,
                name: $response->name ?? null,
                address: $response->address ?? null,
                requestIdentifier: $response->requestIdentifier ?? null,
                requestDate: now(),
            );
        } catch (SoapFault $e) {
            Log::warning('[Spike\ViesValidation] VIES service error', [
                'country_code' => $countryCode,
                'vat_number' => $vatNumber,
                'error' => $e->getMessage(),
            ]);

            return ViesValidationResult::serviceUnavailable($countryCode, $vatNumber);
        }
    }

    protected function sanitizeVatNumber(string $vatNumber, string $countryCode): string
    {
        $vatNumber = preg_replace('/^' . preg_quote($countryCode, '/') . '/i', '', $vatNumber);

        return preg_replace('/[\s\-.]/', '', $vatNumber);
    }

    public function clearCache(string $countryCode, string $vatNumber): void
    {
        $countryCode = strtoupper($countryCode);
        $vatNumber = $this->sanitizeVatNumber($vatNumber, $countryCode);

        Cache::forget("vies_validation:{$countryCode}:{$vatNumber}");
    }
}
