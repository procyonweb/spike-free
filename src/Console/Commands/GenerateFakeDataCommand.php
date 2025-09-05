<?php

namespace Opcodes\Spike\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Contracts\SpikeBillable;

class GenerateFakeDataCommand extends Command
{
    protected $signature = 'spike:fake-data';

    protected $description = 'Generate fake Spike data for the first user.';

    public function handle()
    {
        $model = config('spike.billable_models.0');
        /** @var SpikeBillable|Model $billable */
        $billable = $model::first();

        // go back 30 days and start adding usage transactions
        $counter = 0;

        $date = now()->subDays(31);

        // every 10 days, let's add some credits.
        while ($counter <= 31) {
            if ($counter % 10 === 0) {
                CreditTransaction::forceCreate([
                    'billable_type' => $billable->getMorphClass(),
                    'billable_id' => $billable->getKey(),
                    'type' => CreditTransaction::TYPE_PRODUCT,
                    'credits' => 5_000,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }

            CreditTransaction::forceCreate([
                'billable_type' => $billable->getMorphClass(),
                'billable_id' => $billable->getKey(),
                'type' => CreditTransaction::TYPE_USAGE,
                'credits' => -(rand(200, 300) + ($counter * 10)),
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $date = $date->addDay();
            $counter++;
        }
    }
}
