<?php

namespace Opcodes\Spike\Stripe;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Cashier\SubscriptionItem as CashierSubscriptionItem;
use Opcodes\Spike\Contracts\ProvideHistoryRelatableItemContract;
use Opcodes\Spike\Contracts\SpikeSubscriptionItem;
use Opcodes\Spike\Database\Factories\Stripe\SubscriptionItemFactory;

class SubscriptionItem extends CashierSubscriptionItem implements ProvideHistoryRelatableItemContract, SpikeSubscriptionItem
{
    use HasFactory;

    protected $table = 'stripe_subscription_items';

    protected static function newFactory()
    {
        return SubscriptionItemFactory::new();
    }

    public function getPriceId(): string
    {
        return $this->stripe_price;
    }

    public function provideHistoryId(): string
    {
        return $this->getKey().':'.$this->getPriceId();
    }

    public function provideHistoryType(): string
    {
        return $this->getMorphClass();
    }
}
