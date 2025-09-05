<?php

namespace Opcodes\Spike\Console\Commands;

use Illuminate\Console\Command;

class RenewSubscriptionCreditsCommand extends Command
{
    protected $signature = 'spike:renew-subscription-credits';

    protected $description = 'Renew monthly credits for active subscriptions';

    public function handle()
    {
        $this->warn('This command is deprecated. Use "spike:renew-subscription-providables" instead.');

        $this->info('For backwards compatibility, running "spike:renew-subscription-providables" now...');

        $this->call('spike:renew-subscription-providables');
    }
}
