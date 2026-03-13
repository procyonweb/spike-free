<?php

namespace Opcodes\Spike\Mollie;

use Opcodes\Spike\Contracts\SpikeSubscriptionItem;

class MollieSubscriptionItem implements SpikeSubscriptionItem
{
    public function __construct(
        public string $plan,
        public int $quantity = 1,
        public ?int $subscriptionId = null,
    ) {}

    public function getPriceId(): string
    {
        return $this->plan;
    }

    public function provideHistoryId(): string
    {
        return $this->subscriptionId . '_' . $this->plan;
    }

    public function provideHistoryType(): string
    {
        return 'mollie_subscription_item';
    }
}
