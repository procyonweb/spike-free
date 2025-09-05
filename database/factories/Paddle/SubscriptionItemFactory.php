<?php

namespace Opcodes\Spike\Database\Factories\Paddle;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Opcodes\Spike\Paddle\SubscriptionItem;

class SubscriptionItemFactory extends Factory
{
    protected $model = SubscriptionItem::class;

    public function definition(): array
    {
        return [
            'product_id' => 'prod_'.Str::random(14),
            'price_id' => 'pri_'.Str::random(14),
            'status' => 'active',
            'quantity' => null,
        ];
    }
}
