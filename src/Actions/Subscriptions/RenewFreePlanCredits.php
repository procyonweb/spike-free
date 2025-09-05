<?php

namespace Opcodes\Spike\Actions\Subscriptions;

use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\Contracts\SpikeBillable;
use Illuminate\Support\Facades\Log;
use Opcodes\Spike\Facades\Credits;

class RenewFreePlanCredits
{
    /**
     * @param SpikeBillable|Model $billable
     * @param bool $debugLog
     * @return void
     * @throws \Exception
     */
    public function execute(Model $billable, bool $debugLog = false): void
    {
        $plan = $billable->currentSubscriptionPlan();

        if (! $plan->isFree()) {
            return;
        }

        $providesCreated = app(ProvideSubscriptionPlanMonthlyProvides::class)->handle($plan, $billable);

        if ($providesCreated) {
            Credits::billable($billable)->clearCache();
        }

        if ($providesCreated && $debugLog) {
            Log::debug(sprintf(
                '[%s:%s] Free plan providables renewed.',
                get_class($billable),
                $billable->getKey()
            ));
        }
    }
}
