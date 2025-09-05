<?php

namespace Opcodes\Spike\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\CreditBalance;
use Opcodes\Spike\CreditManager;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\CreditType;

/**
 * @method static SpikeBillable|Model getBillable()
 * @method static CreditManager billable(SpikeBillable|null $billable = null)
 * @method static CreditManager type(CreditType|string $creditType)
 * @method static int balance()
 * @method static Collection|CreditBalance[] allBalances()
 * @method static CreditTransaction add(int $credits, string|array|null $notesOrAttributes = null, array|null $attributes = [])
 * @method static CreditTransaction remove(int $credits, string|array|null $notesOrAttributes = null, array|null $attributes = [])
 * @method static void spend(int $credits, string $notes = null)
 * @method static bool canSpend(int $credits)
 * @method static CreditTransaction currentUsageTransaction()
 * @method static CreditTransaction|null currentSubscriptionTransaction()
 * @method static void expireCurrentUsageTransactions()
 * @method static void clearCache()
 * @method static void allowNegativeBalance(mixed $callback = null)
 * @method static bool isNegativeBalanceAllowed()
 * @method static void clearCustomCallbacks()
 */
class Credits extends Facade
{
    protected static function getFacadeAccessor()
    {
        return CreditManager::class;
    }
}
