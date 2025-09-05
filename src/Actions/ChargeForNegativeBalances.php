<?php

namespace Opcodes\Spike\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\CreditBalance;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;

class ChargeForNegativeBalances
{
    /**
     * @param SpikeBillable $billable
     * @return void
     */
    public function handle(mixed $billable, bool $debugLog = false)
    {
        $balances = $billable->credits()->allBalances();

        $creditsToChargeFor = collect($balances)
            ->filter(function (CreditBalance $balance) {
                return $balance->balance() < 0
                    && $balance->type()->shouldChargeNegativeBalance();
            })->values();

        if ($creditsToChargeFor->isEmpty()) {
            return;
        }

        if (! Spike::paymentProvider()->isStripe()) {
            Log::warning("Billable has negative credit balances, but payment provider does not support offline charges. Credits will not be charged for.", [
                'billable_type' => $billable->getMorphClass(),
                'billable_id' => $billable->getKey(),
                'credits' => $creditsToChargeFor->toArray(),
            ]);

            return;
        }

        DB::transaction(function () use ($creditsToChargeFor, $billable, $debugLog) {
            PaymentGateway::invoiceAndPayItems(
                $creditsToChargeFor->mapWithKeys(function (CreditBalance $balance) {
                    $priceId = $balance->type()->priceId();

                    if (empty($priceId)) {
                        throw new \RuntimeException('No payment provider price ID found for credit type "' . $balance->type()->type . '". Cannot charge for the negative balance.');
                    }

                    return [$priceId => abs($balance->balance())];
                })->toArray()
            );

            /** @var CreditBalance $creditBalanceToChargeFor */
            foreach ($creditsToChargeFor as $creditBalanceToChargeFor) {
                CreditTransaction::create([
                    'billable_type' => $billable->getMorphClass(),
                    'billable_id' => $billable->getKey(),
                    'type' => CreditTransaction::TYPE_PRODUCT,
                    'credit_type' => $creditBalanceToChargeFor->type()->type,
                    'credits' => abs($creditBalanceToChargeFor->balance()),
                ]);
            }

            if ($debugLog) {
                Log::debug(sprintf(
                    '[%s:%s] Charged for negative balances.',
                    get_class($billable),
                    $billable->getKey(),
                ));
            }
        });

        Credits::billable($billable)->clearCache();
    }
}
