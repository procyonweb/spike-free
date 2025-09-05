<?php

namespace Opcodes\Spike\Console\Commands;

use Illuminate\Console\Command;
use Opcodes\Spike\Facades\Credits;

class ClearCreditsCacheCommand extends Command
{
    protected $signature = 'spike:clear-credits-cache';

    protected $description = 'Clear the credits cache for all billables.';

    public function handle(): void
    {
        foreach (config('spike.billable_models') as $billableModelClass) {
            $billableModelClass::eachById(function ($billable) {
                Credits::billable($billable)->clearCache();
            });

            $this->info("Cleared credits cache for {$billableModelClass}.");
        }
    }
}
