<?php

namespace Opcodes\Spike\Actions\Subscriptions;

use Illuminate\Console\OutputStyle;
use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\Actions\ChargeForNegativeBalances;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\PaymentGateway;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessBillableSubscriptionRenewalAction
{
    /**
     * Process a billable's subscription renewal.
     *
     * @param SpikeBillable|Model $billable
     * @param int $verbosity
     * @param OutputStyle|null $output
     * @return void
     */
    public function execute(
        SpikeBillable|Model $billable,
        int $verbosity = 0,
        ?OutputStyle $output = null
    ) {
        $modelClass = get_class($billable);

        $spikeSubscription = $billable->subscriptionManager()->getSubscription();

        if ($spikeSubscription && $spikeSubscription->isPastDue()) {
            if ($this->shouldShowDebugInfo($verbosity)) {
                $this->logDebug($output, "  [$modelClass:{$billable->getKey()}] Subscription is past due. Expiring providables...");
            }

            app(ExpireSubscriptionProvidables::class)
                ->handle($billable, $spikeSubscription, now()->subSecond(), $this->shouldShowDebugInfo($verbosity));
        }

        if (! $billable->isSubscribed()) {
            if ($this->shouldShowDebugInfo($verbosity)) {
                $this->logDebug($output, "  [$modelClass:{$billable->getKey()}] Billable is not subscribed. Renewing free plan providables if needed...");
            }

            app(RenewFreePlanCredits::class)->execute($billable, $this->shouldShowDebugInfo($verbosity));
            return;
        }

        if (! $spikeSubscription) {
            if ($this->shouldShowDebugInfo($verbosity)) {
                $this->logDebug($output, "  [$modelClass:{$billable->getKey()}] Billable is not subscribed. Skipping...");
            }

            return;
        }

        if (
            PaymentGateway::billable($billable)->getRenewalDate()?->isToday()
            || $spikeSubscription->ends_at?->isPast()
        ) {
            if ($this->shouldShowDebugInfo($verbosity)) {
                $this->logDebug($output, "  [$modelClass:{$billable->getKey()}] Charging for negative balances if needed...");
            }

            app(ChargeForNegativeBalances::class)->handle($billable, $this->shouldShowDebugInfo($verbosity));
        }

        if ($this->isOnGracePeriod($spikeSubscription)) {
            if ($this->shouldShowDebugInfo($verbosity)) {
                $this->logDebug($output, "  [$modelClass:{$billable->getKey()}] Subscription is on grace period. Expiring providables...");
            }

            app(ExpireSubscriptionProvidables::class)
                ->handle($billable, $spikeSubscription, null, $this->shouldShowDebugInfo($verbosity));
        } else {
            if ($this->shouldShowDebugInfo($verbosity)) {
                $this->logDebug($output, "  [$modelClass:{$billable->getKey()}] Subscription is active. Renewing providables if needed...");
            }

            app(RenewSubscriptionProvidables::class)
                ->handle($billable, $spikeSubscription, $this->shouldShowDebugInfo($verbosity));
        }
    }

    /**
     * Check if the subscription is on grace period.
     * 
     * @param mixed $subscription
     * @return bool
     */
    protected function isOnGracePeriod($subscription): bool
    {
        // Using null coalescing to avoid null reference if method doesn't exist
        return method_exists($subscription, 'onGracePeriod') 
            ? $subscription->onGracePeriod() 
            : ($subscription->ends_at && $subscription->ends_at->isFuture());
    }

    /**
     * Determine if debug information should be displayed.
     *
     * @param int $verbosity
     * @return bool
     */
    protected function shouldShowDebugInfo(int $verbosity): bool
    {
        return $verbosity >= OutputInterface::VERBOSITY_VERBOSE;
    }

    /**
     * Log debug information to both the log and console output if available.
     *
     * @param OutputStyle|null $output
     * @param string $message
     * @return void
     */
    protected function logDebug(?OutputStyle $output, string $message, string $type = 'debug'): void
    {
        if ($output !== null) {
            switch ($type) {
                case 'debug':
                    $output->writeln("$message");
                    break;
                case 'info':
                    $output->writeln("<info>$message</info>");
                    break;
                case 'warning':
                    $output->writeln("<warning>$message</warning>");
                    break;
                case 'error':
                    $output->writeln("<error>$message</error>");
                    break;
            }
        }
    }
} 