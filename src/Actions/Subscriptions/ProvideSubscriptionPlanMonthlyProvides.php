<?php

namespace Opcodes\Spike\Actions\Subscriptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Opcodes\Spike\Contracts\Providable;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Contracts\SpikeSubscriptionItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\ProvideHistory;
use Opcodes\Spike\SubscriptionPlan;

class ProvideSubscriptionPlanMonthlyProvides
{
    /**
     * Provide the monthly provides from the subscription plan.
     *
     * @param SubscriptionPlan $plan
     * @param SpikeBillable|Model $billable
     * @param SpikeSubscriptionItem|null $subscriptionItem
     * @return bool Whether new provides were created.
     */
    public function handle(SubscriptionPlan $plan, Model $billable, ?SpikeSubscriptionItem $subscriptionItem = null): bool
    {
        if ($plan->isPaid() && is_null($subscriptionItem)) {
            throw new \InvalidArgumentException('You must provide a subscription item when providing a paid subscription plan.');
        }

        $createdProvides = false;

        $relatedItem = $plan->isFree() ? $plan : $subscriptionItem;

        // If the related item is a subscription item, that means we're renewing a paid plan/subscription.
        // In that case, we need to check whether we've recently provided the free plan credits, and if so,
        // expire them as we'll soon be providing the paid plan credits instead.
        if ($relatedItem instanceof SpikeSubscriptionItem) {
            $this->removeRecentFreePlanProvides($billable);
        }

        /** @var Providable $provide */
        foreach ($plan->provides_monthly as $provide) {
            if (ProvideHistory::hasProvidedMonthly($relatedItem, $provide, $billable)) {
                if ($this->restorePreviouslyExpiredCreditTransaction($provide, $billable)) {
                    $createdProvides = true;
                }

                continue;
            }

            DB::beginTransaction();

            try {
                $provide->provideMonthlyFromSubscriptionPlan($plan, $billable);

                ProvideHistory::createSuccessfulProvide(
                    $relatedItem, $provide, $billable
                );

                DB::commit();

                $createdProvides = true;

            } catch (\Exception $exception) {

                DB::rollBack();

                ProvideHistory::createFailedProvide(
                    $relatedItem, $provide, $billable, $exception
                );

                Log::error($exception);
            }
        }

        return $createdProvides;
    }

    protected function restorePreviouslyExpiredCreditTransaction(Providable $provide, $billable): bool
    {
        if (! $provide instanceof CreditAmount) {
            return false;
        }

        $creditTransaction = $provide->getLatestCreditTransaction($billable);

        if ($creditTransaction && $creditTransaction->expired()) {
            $creditTransaction->expires_at = null;
            $creditTransaction->save();
            Credits::billable($billable)->type($creditTransaction->credit_type)->clearCache();
            return true;
        }

        return false;
    }

    protected function removeRecentFreePlanProvides($billable): void
    {
        $freePlan = Spike::subscriptionPlans($billable)
            ->filter(fn (SubscriptionPlan $plan) => $plan->isFree())
            ->first();

        if (! $freePlan) {
            return;
        }

        foreach ($freePlan->provides_monthly as $provide) {
            if (! $provide instanceof CreditAmount) {
                continue;
            }

            if (ProvideHistory::hasProvidedMonthly($freePlan, $provide, $billable)) {
                $creditTransaction = $provide->getLatestCreditTransaction($billable);
                if ($creditTransaction) {
                    $creditTransaction->expireAt($creditTransaction->created_at);
                    Credits::billable($billable)->type($creditTransaction->credit_type)->clearCache();
                }
            }
        }
    }
}
