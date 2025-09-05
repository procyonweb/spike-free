<?php

namespace Opcodes\Spike\Database\Factories\Paddle;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Opcodes\Spike\Paddle\Subscription;

class SubscriptionFactory extends Factory
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
        /** @noinspection PhpUndefinedMethodInspection */
        return [
            'billable_id' => ($this->model)::factory(),
            'billable_type' => (new $this->model)->getMorphClass(),
            'type' => 'default',
            'paddle_id' => 'sub_'.Str::random(26),
            'status' => 'active',
            'trial_ends_at' => null,
            'paused_at' => null,
            'ends_at' => null,
        ];
    }

    public function withoutPaymentCard()
    {
        return $this->state(fn(array $attributes) => [
            'paddle_id' => '',
        ]);
    }
}
