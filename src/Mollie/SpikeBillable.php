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
use Opcodes\Spike\Vat\ManagesVat;

/**
 * @mixin Billable|Model
 *
 * @property-read Collection|Subscription[] $subscriptions
 *
 * @method Subscription subscription(string $name = 'default')
 */
trait SpikeBillable
{
    use Billable, ManagesCredits {
        ManagesCredits::credits insteadof Billable;
        Billable::credits as mollieCredits;
    }
    use ManagesPurchases;
    use ManagesSubscriptions;
    use ManagesPromotionCode;
    use ManagesVat;

    public function spikeCacheIdentifier(): string
    {
        return $this->getMorphClass() . ':' . $this->getKey();
    }

    public function spikeEmail()
    {
        return $this->mollieEmail();
    }

    public function spikeOrders()
    {
        return $this->morphMany(Order::class, 'owner');
    }

    public function spikeInvoices()
    {
        return $this->spikeOrders()
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
