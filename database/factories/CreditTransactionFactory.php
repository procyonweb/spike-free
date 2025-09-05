<?php

namespace Opcodes\Spike\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\CreditType;
use Opcodes\Spike\Contracts\SpikeBillable;

class CreditTransactionFactory extends Factory
{
    protected $model = CreditTransaction::class;

    public function definition(): array
    {
        return [
            'credit_type' => CreditType::default()->type,
            'type' => CreditTransaction::TYPE_ADJUSTMENT,
            'credits' => $this->faker->numberBetween(-5, 20) * 10,
        ];
    }

    /**
     * @param SpikeBillable|Model $billable
     * @return CreditTransactionFactory
     */
    public function forBillable($billable)
    {
        return $this->state(fn(array $attributes) => [
            'billable_type' => $billable->getMorphClass(),
            'billable_id' => $billable->getKey(),
        ]);
    }

    public function type(CreditType|string $type)
    {
        $type = CreditType::make($type);

        if (! $type->isValid()) {
            throw new \InvalidArgumentException("Invalid credit type: \"{$type->type}\". Please make sure it is configured.");
        }

        return $this->state(fn(array $attributes) => [
            'credit_type' => $type->type
        ]);
    }

    public function expired()
    {
        return $this->state(fn(array $attributes) => [
            'expires_at' => now()->subDays($this->faker->randomNumber())
        ]);
    }

    public function usage()
    {
        return $this->state(fn(array $attributes) => [
            'type' => CreditTransaction::TYPE_USAGE,
            'credits' => $this->faker->numberBetween(-10, -5) * 5,
        ]);
    }

    public function purchase()
    {
        return $this->state(fn(array $attributes) => [
            'type' => CreditTransaction::TYPE_PRODUCT,
            'credits' => $this->faker->numberBetween(5, 20) * 10,
        ]);
    }

    public function product()
    {
        return $this->purchase();
    }

    public function subscription()
    {
        return $this->state(fn(array $attributes) => [
            'type' => CreditTransaction::TYPE_SUBSCRIPTION,
            'credits' => $this->faker->numberBetween(5, 20) * 10,
        ]);
    }

    public function adjustment()
    {
        return $this->state(fn(array $attributes) => [
            'type' => CreditTransaction::TYPE_ADJUSTMENT
        ]);
    }
}
