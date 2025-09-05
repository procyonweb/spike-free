<?php

namespace Opcodes\Spike\Tests\Fixtures;

use Opcodes\Spike\Contracts\Providable;
use Opcodes\Spike\Product;
use Opcodes\Spike\SubscriptionPlan;

class SampleProvidable implements Providable
{
    public int $providedMonthlySubscriptionCount = 0;
    public int $providedProductCount = 0;

    public static function __set_state(array $data): Providable
    {
        return new static;
    }

    public function key(): string
    {
        return get_class($this);
    }

    public function name(): string
    {
        return 'test';
    }

    public function icon(): ?string
    {
        return null;
    }

    public function toString(): string
    {
        return 'test';
    }

    public function isSameProvidable(Providable $providable): bool
    {
        return get_class($providable) === get_class($this);
    }

    public function provideOnceFromProduct(Product $product, $billable): void
    {
        $this->providedProductCount++;
    }

    public function provideMonthlyFromSubscriptionPlan(SubscriptionPlan $subscriptionPlan, $billable): void
    {
        $this->providedMonthlySubscriptionCount++;
    }
}
