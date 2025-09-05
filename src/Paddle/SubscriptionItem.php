<?php

namespace Opcodes\Spike\Paddle;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Paddle\SubscriptionItem as CashierSubscriptionItem;
use Opcodes\Spike\Contracts\ProvideHistoryRelatableItemContract;
use Opcodes\Spike\Contracts\SpikeSubscriptionItem;
use Opcodes\Spike\Database\Factories\Paddle\SubscriptionItemFactory;

class SubscriptionItem extends CashierSubscriptionItem implements ProvideHistoryRelatableItemContract, SpikeSubscriptionItem
{
    use HasFactory;

    protected $table = 'paddle_subscription_items';

    protected static function newFactory()
    {
        return SubscriptionItemFactory::new();
    }

    public function getPriceId(): string
    {
        return $this->price_id;
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
