<?php

namespace Opcodes\Spike\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Opcodes\Spike\Actions\Subscriptions\ProcessBillableSubscriptionRenewalAction;
use Opcodes\Spike\Contracts\SpikeBillable;

class ProcessBillableSubscriptionRenewal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param Collection $billables A collection of SpikeBillable|Model instances
     * @param int $verbosity
     * @return void
     */
    public function __construct(
        public Collection $billables,
        public int $verbosity = 0,
    ) {
    }

    /**
     * Execute the job.
     *
     * @param ProcessBillableSubscriptionRenewalAction $action
     * @return void
     */
    public function handle(ProcessBillableSubscriptionRenewalAction $action)
    {
        foreach ($this->billables as $billable) {
            if ($billable instanceof SpikeBillable || $billable instanceof Model) {
                $action->execute($billable, $this->verbosity);
            }
        }
    }
} 