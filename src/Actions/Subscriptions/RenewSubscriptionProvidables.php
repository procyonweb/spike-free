<?php

namespace Opcodes\Spike\Actions\Subscriptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;

class RenewSubscriptionProvidables
{
    /**
     * @param Model|SpikeBillable $billable
     * @param SpikeSubscription $subscription
     * @param bool $debugLog
     * @return void
     * @throws \Exception
     */
    public function handle(Model $billable, SpikeSubscription $subscription, bool $debugLog = false)
    {
        if ($billable->isNot($subscription->getBillable())) {
            throw new \InvalidArgumentException('Trying to renew providables for a subscription that does not belong to this billable.');
        }

        DB::transaction(function () use ($billable, $subscription, $debugLog) {
            $subscriptionPlan = Spike::findSubscriptionPlan(
                $subscription->getPriceId(),
                $billable
            );

            $providesCreated = app(ProvideSubscriptionPlanMonthlyProvides::class)->handle(
                $subscriptionPlan,
                $billable,
                $subscription->items->first()
            );

            if ($providesCreated) {
                Credits::billable($billable)->clearCache();
            }

            if ($providesCreated && $debugLog) {
                Log::debug(sprintf(
                    '[%s:%s] Subscription is active. Renewed providables.',
                    get_class($billable),
                    $billable->getKey()
                ));
            }

            if (! $subscription->hasPaymentCard()) {
                // refresh the renewal date
                $monthsSinceCreation = floor($subscription->created_at->floatDiffInMonths(now()->addDay()));
                $newRenewal = $subscription->created_at->copy()->addMonthsNoOverflow($monthsSinceCreation + 1);

                $subscription->fill([
                    'renews_at' => $newRenewal
                ])->save();
            }
        });
    }
}
