<?php

namespace Opcodes\Spike\Stripe\Actions;

use Laravel\Cashier\Checkout;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Stripe\Interfaces\SubscriptionCheckoutRedirectInterface;
use Opcodes\Spike\Stripe\PaymentGateway;
use Opcodes\Spike\SubscriptionPlan;

class SubscriptionCheckoutRedirect implements SubscriptionCheckoutRedirectInterface
{
    public function handle(SubscriptionPlan $plan): Checkout
    {
        $subscriptionBuilder = Spike::resolve()->newSubscription(
            PaymentGateway::$subscriptionName,
            $plan->payment_provider_price_id
        );

        if (Spike::stripeAllowDiscounts()) {
            $subscriptionBuilder = $subscriptionBuilder->allowPromotionCodes();
        }

        $options = [
            'success_url' => route('spike.subscribe', ['success' => true]),
            'cancel_url' => route('spike.subscribe', ['canceled' => true]),
        ];

        if ($locale = config('spike.stripe.checkout.default_locale')) {
            $options['locale'] = $locale;
        }

        return $subscriptionBuilder->checkout($options);
    }
}
