<?php

namespace Opcodes\Spike\Database\Factories\Stripe;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Opcodes\Spike\Stripe\Subscription;
use Opcodes\Spike\Stripe\SubscriptionItem;

class SubscriptionItemFactory extends Factory
{
    protected $model = SubscriptionItem::class;

    public function definition(): array
    {
        return [
            'stripe_subscription_id' => Subscription::factory(),
            'stripe_id' => 'si_'.Str::random(14),
            'stripe_product' => 'prod_'.Str::random(14),
            'stripe_price' => 'price_'.Str::random(24),
            'quantity' => null,
        ];
    }
}
