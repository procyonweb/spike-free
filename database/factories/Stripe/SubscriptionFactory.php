<?php

namespace Opcodes\Spike\Database\Factories\Stripe;

use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Opcodes\Spike\Stripe\Subscription;

class SubscriptionFactory extends \Laravel\Cashier\Database\Factories\SubscriptionFactory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $model = Cashier::$customerModel;

        /** @noinspection PhpUndefinedMethodInspection */
        return [
            (new $model)->getForeignKey() => ($model)::factory(),
            'type' => 'default',
            'stripe_id' => 'sub_'.Str::random(40),
            'stripe_status' => \Stripe\Subscription::STATUS_ACTIVE,
            'stripe_price' => null,
            'quantity' => null,
            'trial_ends_at' => null,
            'ends_at' => null,
        ];
    }

    public function withoutPaymentCard()
    {
        return $this->state(fn(array $attributes) => [
            'stripe_id' => '',
        ]);
    }
}
