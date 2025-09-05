<?php

namespace Opcodes\Spike\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Opcodes\Spike\CreditManager;
use Opcodes\Spike\Product;
use Opcodes\Spike\SubscriptionManager;
use Opcodes\Spike\SubscriptionPlan;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 * @mixin \Laravel\Cashier\Billable
 * @mixin \Laravel\Paddle\Billable
 */
interface SpikeBillable
{
    /****************************************
     * From the ManagesSubscriptions trait
     ****************************************/

    public function subscriptionManager(): SubscriptionManager;

    public function isSubscribed(?SubscriptionPlan $plan = null): bool;

    public function isSubscribedTo(SubscriptionPlan $plan): bool;

    public function subscribeTo(SubscriptionPlan $plan, bool $requirePaymentCard = true): ?SpikeSubscription;

    public function subscribeWithoutPaymentTo(SubscriptionPlan $plan): ?SpikeSubscription;

    public function cancelSubscription(): ?SpikeSubscription;

    public function cancelSubscriptionNow(): ?SpikeSubscription;

    public function resumeSubscription(): ?SpikeSubscription;

    public function getSubscription(): ?SpikeSubscription;

    public function currentSubscriptionPlan(): ?SubscriptionPlan;

    public function subscriptionRenewalDate(): ?CarbonInterface;

    public function subscriptionMonthlyRenewalDate(): ?CarbonInterface;

    /****************************************
     * From the ManagesPurchases trait
     ****************************************/

    public function purchases(): Collection;

    public function groupedPurchases(): Collection;

    public function hasPurchased(Product|string $product): bool;

    /****************************************
     * From the ManagesCredits trait
     ****************************************/

    public function creditManager(): CreditManager;

    public function credits(): CreditManager;

    /****************************************
     * Laravel Cashier methods
     ****************************************/

    /**
     * @return SpikeSubscription|null
     */
    public function subscription();

    /****************************************
     * Spike methods
     ****************************************/

    public function spikeCacheIdentifier(): string;

    public function spikeEmail();

    public function spikeInvoices();
}
