<?php

namespace Opcodes\Spike\Observers;

use Opcodes\Spike\Actions\Subscriptions\RenewFreePlanCredits;

class BillableModelObserver
{
    public function created($model)
    {
        app(RenewFreePlanCredits::class)->execute($model);
    }
}
