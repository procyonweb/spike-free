<?php

namespace Opcodes\Spike\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Opcodes\Spike\Cart;
use Opcodes\Spike\Contracts\SpikeBillable;

class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'paid_at' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    /**
     * @param SpikeBillable|Model $billable
     * @return self
     */
    public function forBillable(Model $billable): self
    {
        return $this->state([
            'billable_type' => $billable->getMorphClass(),
            'billable_id' => $billable->getKey(),
        ]);
    }

    public function paid(): self
    {
        return $this->state([
            'paid_at' => Carbon::now(),
        ]);
    }
}
