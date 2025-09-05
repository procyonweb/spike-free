<?php

namespace Opcodes\Spike\Actions\Migrations;

use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\ProvideHistory;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Stripe\SubscriptionItem;

class MigrateProvideHistoryFromV2
{
    public function handle(): void
    {
        CreditTransaction::query()
            ->whereIn('type', [CreditTransaction::TYPE_SUBSCRIPTION, CreditTransaction::TYPE_PRODUCT])
            ->with(['billable', 'cartItem', 'subscriptionItem'])
            ->eachById(function (CreditTransaction $creditTransaction) {

                // we want to add a ProvideHistory entry for this month of the subscription.
                /** @var SpikeBillable|Model $billable */
                $billable = $creditTransaction->billable;

                if ($creditTransaction->cartItem) {
                    ProvideHistory::createSuccessfulProvide(
                        $creditTransaction->cartItem,
                        CreditAmount::make($creditTransaction->credits),
                        $billable,
                        $creditTransaction->created_at,
                    );
                } elseif ($creditTransaction->subscriptionItem) {
                    ProvideHistory::createSuccessfulProvide(
                        SubscriptionItem::find($creditTransaction->subscriptionItem->id),
                        CreditAmount::make($creditTransaction->credits),
                        $billable,
                        $creditTransaction->created_at,
                    );
                }

            });
    }

    public function rollback(): void
    {
        ProvideHistory::truncate();
    }
}
