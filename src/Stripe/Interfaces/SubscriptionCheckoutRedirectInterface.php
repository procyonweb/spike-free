<?php

namespace Opcodes\Spike\Stripe\Interfaces;

use Laravel\Cashier\Checkout;
use Opcodes\Spike\SubscriptionPlan;

interface SubscriptionCheckoutRedirectInterface
{
    public function handle(SubscriptionPlan $plan): Checkout;
}
