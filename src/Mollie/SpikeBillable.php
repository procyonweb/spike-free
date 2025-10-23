<?php

namespace Opcodes\Spike\Mollie;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;
use Opcodes\Spike\SpikeInvoice;
use Opcodes\Spike\Traits\ManagesCredits;
use Opcodes\Spike\Traits\ManagesPromotionCode;
use Opcodes\Spike\Traits\ManagesPurchases;
use Opcodes\Spike\Traits\ManagesSubscriptions;

/**
 * @mixin Billable|Model
 *
 * @property-read Collection|Subscription[] $subscriptions
 *
 * @method Subscription subscription(string $name = 'default')
 */
trait SpikeBillable
{
    use Billable;
    use ManagesCredits;
    use ManagesPurchases;
    use ManagesSubscriptions;
    use ManagesPromotionCode;

    public function spikeCacheIdentifier(): string
    {
        return $this->getMorphClass() . ':' . $this->getKey();
    }

    public function spikeEmail()
    {
        return $this->mollieEmail();
    }

    public function orders()
    {
        return $this->morphMany(Order::class, 'billable');
    }

    public function spikeInvoices()
    {
        return $this->orders()
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($order) => new SpikeInvoice(
                id: $order->id,
                number: $order->number ?? $order->id,
                date: $order->created_at,
                status: $order->mollie_payment_status,
                total: $order->getTotal()
            ));
    }
}
