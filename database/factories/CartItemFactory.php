<?php

namespace Opcodes\Spike\Database\Factories;

use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\Tests\Fixtures\Stripe\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return [
            'product_id' => Str::random(10),
            'quantity' => $this->faker->numberBetween(1, 5),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'cart_id' => Cart::factory()->for(User::factory(), 'billable'),
        ];
    }
}
