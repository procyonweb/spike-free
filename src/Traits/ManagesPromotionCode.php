<?php

namespace Opcodes\Spike\Traits;

use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\PromotionCode;
use Opcodes\Spike\Facades\PaymentGateway;

trait ManagesPromotionCode
{
    public function usePromotionCode(?PromotionCode $promotionCode = null): void
    {
        session()->put('subscription_promotion_code_id', optional($promotionCode)->id);
        Cache::driver('array')->forget('spike.subscription_promotion_code');
    }

    public function removeStripePromotionCode(): void
    {
        session()->forget('subscription_promotion_code_id');
        Cache::driver('array')->forget('spike.subscription_promotion_code');
    }

    public function stripePromotionCode(): ?PromotionCode
    {
        if (($id = session('subscription_promotion_code_id')) && PaymentGateway::provider()->isStripe()) {
            $cacheKey = 'spike.subscription_promotion_code';
            $promotionCode = Cache::driver('array')->remember($cacheKey, 10, function () use ($id) {
                return PaymentGateway::findStripePromotionCode($id);
            });

            if (is_null($promotionCode)) {
                // such promotion code doesn't exist anymore, let's unset it:
                $this->removeStripePromotionCode();
            }

            return $promotionCode;
        }

        return null;
    }
}
