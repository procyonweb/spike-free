<?php

namespace Opcodes\Spike\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Opcodes\Spike\Actions\Subscriptions\ProcessBillableSubscriptionRenewalAction;
use Opcodes\Spike\Actions\VerifyBillableUsesTrait;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Jobs\ProcessBillableSubscriptionRenewal;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to renew providables for active subscriptions.
 * 
 * This command iterates through all billable models configured in spike.php and processes
 * each one. The actual processing is offloaded to a job (ProcessBillableSubscriptionRenewal)
 * which can be either processed synchronously (default) or queued for asynchronous processing
 * using the --queue option.
 * 
 * Command options:
 * --queue       : When set, the jobs will be queued instead of processed synchronously
 * --queue-name= : (Optional) The name of the queue to push jobs to. When omitted, the default queue is used.
 * --chunk-size= : (Optional) Number of billables to process in each chunk (default: 100)
 */
class RenewSubscriptionProvidablesCommand extends Command
{
    protected $signature = 'spike:renew-subscription-providables 
                            {--queue : Whether the jobs should be queued}
                            {--queue-name= : The name of the queue to push jobs to}
                            {--chunk-size=100 : Number of billables to process in each chunk}';

    protected $description = 'Renew monthly providables for active subscriptions';

    public function __construct(
        protected VerifyBillableUsesTrait $verifyBillableUsesTrait,
        protected ProcessBillableSubscriptionRenewalAction $processAction,
    )
    {
        parent::__construct();
    }

    /**
     * Execute the command to renew subscription providables.
     *
     * @return int
     */
    public function handle()
    {
        if (empty(config('spike.billable_models'))) {
            $this->error("You have no billable models configured. We don't know which billables to renew providables for.");
            $this->line("Please add your billable models to the 'billable_models' array in your spike.php config file.");
            return Command::FAILURE;
        }

        $this->info("Renewing providables for active subscriptions...");
        
        if ($this->shouldShowDebugInfo()) {
            $this->info("Billable models configured: " . implode(', ', config('spike.billable_models', [])));
            Log::debug("Starting the process of renewing providables for active subscriptions...", [
                'billable_models' => config('spike.billable_models', []),
            ]);
        }

        $shouldQueue = $this->option('queue');
        $queueName = $this->option('queue-name');
        $chunkSize = (int) $this->option('chunk-size');
        $processedCount = 0;

        /** @var Model $modelClass */
        foreach (config('spike.billable_models', []) as $modelClass) {

            $this->verifyBillableUsesTrait->handle(new $modelClass);

            $this->logModelCount($modelClass);

            (new $modelClass)->newModelQuery()->chunk($chunkSize, function (Collection $billables) use ($modelClass, $shouldQueue, $queueName, &$processedCount) {
                if ($shouldQueue) {
                    $job = new ProcessBillableSubscriptionRenewal(
                        $billables, 
                        $this->output->getVerbosity()
                    );

                    if ($queueName) {
                        Queue::pushOn($queueName, $job);

                        $this->debugLog("[{$modelClass}] Queued " . $billables->count() . " billables for processing on queue: '$queueName'.");
                    } else {
                        Queue::push($job);

                        $this->debugLog("[{$modelClass}] Queued " . $billables->count() . " billables for processing on default queue.");
                    }
                } else {
                    foreach ($billables as $billable) {
                        $this->processAction->execute(
                            $billable,
                            $this->output->getVerbosity(),
                            $this->output,
                        );
                    }
                }

                $processedCount += $billables->count();
            });
        }

        return $this->success($processedCount);
    }

    protected function logModelCount(string $modelClass): void
    {
        $count = (new $modelClass)->newModelQuery()->count();
        $this->debugLog("Found $count billables to process for [$modelClass]", 'info', true);
        $this->newLine();
    }

    protected function success(int $processedCount): int
    {
        $shouldQueue = $this->option('queue');
        $queueName = $this->option('queue-name');

        if ($shouldQueue) {
            if ($queueName) {
                $this->debugLog("Done. $processedCount billables have been queued for renewal on queue: '$queueName'.", 'info', true);
            } else {
                $this->debugLog("Done. $processedCount billables have been queued for renewal.", 'info', true);
            }
        } else {
            $this->debugLog("Done. $processedCount billables have been processed synchronously.", 'info', true);
        }

        return Command::SUCCESS;
    }

    protected function debugLog(string $message, ?string $type = 'debug', bool $forceLog = false): void
    {
        if ($this->shouldShowDebugInfo() || $forceLog) {
            Log::$type(trim($message));

            switch ($type) {
                case 'info':
                    $this->info($message);
                    break;
                case 'warning':
                    $this->warn($message);
                    break;
                case 'error':
                    $this->error($message);
                    break;
                default:
                    $this->line($message);
                    break;
            }
        }
    }

    /**
     * Determine if debug information should be displayed.
     *
     * @return bool
     */
    protected function shouldShowDebugInfo(): bool
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }
}
