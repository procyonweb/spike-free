<?php

namespace Opcodes\Spike\Actions\Subscriptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\Contracts\SpikeSubscriptionItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\Spike;
use Illuminate\Support\Facades\Log;
use Opcodes\Spike\Facades\Credits;

class ExpireSubscriptionProvidables
{
    public function handle(Model $billable, SpikeSubscription $subscription, $expireAt = null, bool $debugLog = false): void
    {
        $shouldClearCache = false;

        DB::transaction(function () use ($billable, $subscription, $expireAt, $debugLog, &$shouldClearCache) {
            /** @var SpikeSubscriptionItem $subscriptionItem */
            $subscriptionPlan = Spike::findSubscriptionPlan(
                $subscription->getPriceId(),
                $billable
            );

            foreach ($subscriptionPlan->provides_monthly as $providable) {
                if (! $providable instanceof CreditAmount) {
                    continue;
                }

                $billable->credits()
                    ->type($providable->getType())
                    ->currentSubscriptionTransaction()
                    ?->expireAt($expireAt ?? $subscription->ends_at);

                $shouldClearCache = true;
            }

            if ($debugLog) {
                Log::debug(sprintf(
                    '[%s:%s] Subscription providables expired.',
                    get_class($billable),
                    $billable->getKey()
                ));
            }
        });

        if ($shouldClearCache) {
            Credits::billable($billable)->clearCache();
        }
    }
}
