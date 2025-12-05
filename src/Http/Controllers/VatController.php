<?php

namespace Opcodes\Spike\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Vat\VatValidationException;
use Opcodes\Spike\Vat\ViesValidationService;

class VatController
{
    public function show(): JsonResponse
    {
        $billable = Spike::resolve();

        return response()->json([
            'billing_country' => $billable->billing_country,
            'vat_id' => $billable->vat_id,
            'vat_id_type' => $billable->vat_id_type,
            'vat_id_verified' => $billable->vat_id_verified,
            'vat_id_verified_at' => $billable->vat_id_verified_at,
            'tax_exempt_status' => $billable->tax_exempt_status,
            'vies_request_identifier' => $billable->vies_request_identifier,
            'vies_company_name' => $billable->vies_company_name,
            'vies_company_address' => $billable->vies_company_address,
            'tax_treatment' => $billable->getTaxTreatment(),
            'can_purchase' => $billable->canPurchase(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'billing_country' => 'required|string|size:2',
            'vat_id' => 'nullable|string|max:20',
        ]);

        $billable = Spike::resolve();

        try {
            $billable->setVatDetails(
                $request->input('billing_country'),
                $request->input('vat_id'),
            );

            return response()->json([
                'success' => true,
                'tax_treatment' => $billable->getTaxTreatment(),
                'billable' => [
                    'billing_country' => $billable->billing_country,
                    'vat_id' => $billable->vat_id,
                    'vat_id_verified' => $billable->vat_id_verified,
                    'tax_exempt_status' => $billable->tax_exempt_status,
                    'vies_request_identifier' => $billable->vies_request_identifier,
                    'vies_company_name' => $billable->vies_company_name,
                    'vies_company_address' => $billable->vies_company_address,
                ],
            ]);
        } catch (VatValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'country_code' => 'required|string|size:2',
            'vat_id' => 'required|string|max:20',
        ]);

        $viesService = app(ViesValidationService::class);
        $result = $viesService->validate(
            $request->input('country_code'),
            $request->input('vat_id'),
        );

        return response()->json([
            'valid' => $result->isValid(),
            'service_available' => $result->isServiceAvailable(),
            'country_code' => $result->countryCode,
            'vat_number' => $result->vatNumber,
            'name' => $result->name,
            'address' => $result->address,
            'request_identifier' => $result->requestIdentifier,
        ]);
    }
}
