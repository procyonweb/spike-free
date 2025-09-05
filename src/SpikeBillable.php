<?php

namespace Opcodes\Spike;

use Illuminate\Support\Facades\Log;

trait SpikeBillable
{
    public static function bootSpikeBillable()
    {
        $errorMessage = "Opcodes\Spike\SpikeBillable has been moved to Opcodes\Spike\Stripe\SpikeBillable." . PHP_EOL
            . "Please update your import, or follow the upgrade guide at https://spike.opcodes.io/docs/3.x/update";

        if (app()->runningInConsole()) {
            Log::warning($errorMessage);
        } else {
            throw new \Exception($errorMessage);
        }
    }
}
