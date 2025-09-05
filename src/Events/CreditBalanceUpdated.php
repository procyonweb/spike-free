<?php

namespace Opcodes\Spike\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\CreditType;
use Opcodes\Spike\Contracts\SpikeBillable;

class CreditBalanceUpdated
{
    use Dispatchable;

    /**
     * @param SpikeBillable $billable
     * @param int $balance
     * @param CreditTransaction $relatedCreditTransaction
     * @param CreditType $creditType
     */
    public function __construct(
        public mixed $billable,
        public int $balance,
        public CreditTransaction $relatedCreditTransaction,
        public CreditType $creditType,
    ) {}
}
