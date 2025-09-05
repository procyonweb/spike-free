<?php

namespace Opcodes\Spike\Stripe\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Coupon;
use Laravel\Cashier\PromotionCode;
use Stripe\Exception\InvalidRequestException;

class StripeService
{
    /**
     * Fetch coupons from Stripe API.
     * 
     * @return Collection<string, Coupon>
     */
    public function fetchCoupons(): Collection
    {
        try {
            $stripeCoupons = Cashier::stripe()->coupons->all([
                'limit' => 100,
                'expand' => ['data.applies_to', 'data.currency_options'],
            ]);

            return collect($stripeCoupons->data)
                ->keyBy('id')
                ->map(fn ($coupon) => new Coupon($coupon));
        } catch (InvalidRequestException $exception) {
            Log::warning('Failed to retrieve Stripe coupon codes.', [
                'exception' => $exception->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Fetch promotion codes from Stripe API.
     * 
     * @return Collection<string, PromotionCode>
     */
    public function fetchPromotionCodes(): Collection
    {
        try {
            $stripePromotionCodes = Cashier::stripe()->promotionCodes->all([
                'limit' => 100,
                'expand' => ['data.coupon', 'data.coupon.applies_to', 'data.coupon.currency_options'],
            ]);

            return collect($stripePromotionCodes->data)
                ->keyBy('id')
                ->map(fn ($promotionCode) => new PromotionCode($promotionCode));
        } catch (InvalidRequestException $exception) {
            Log::warning('Failed to retrieve Stripe promotion codes.', [
                'exception' => $exception->getMessage(),
            ]);

            return collect();
        }
    }
}