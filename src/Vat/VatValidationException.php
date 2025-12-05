<?php

namespace Opcodes\Spike\Vat;

use Exception;

class VatValidationException extends Exception
{
    public static function serviceUnavailable(): static
    {
        return new static('VIES validation service is temporarily unavailable. Please try again later.');
    }

    public static function invalidVatId(string $countryCode, string $vatId): static
    {
        return new static("The VAT ID '{$countryCode}{$vatId}' is not valid according to VIES.");
    }

    public static function vatIdRequired(string $countryCode): static
    {
        return new static("A valid VAT ID is required for B2B transactions in {$countryCode}.");
    }

    public static function vatDetailsRequired(): static
    {
        return new static('VAT details must be configured before proceeding.');
    }

    public static function billingCountryRequired(): static
    {
        return new static('Billing country is required.');
    }
}
