<?php

namespace Opcodes\Spike\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\SubscriptionPlan;

class SubscriptionDeactivated
{
    use Dispatchable;

    /**
     * @param SpikeBillable $billable
     * @param SubscriptionPlan $plan
     */
    public function __construct(
        public mixed $billable,
        public SubscriptionPlan $plan,
    ) {}
}
