<?php

namespace Opcodes\Spike;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Opcodes\Spike\Contracts\SpikeBillable;

class BalanceAggregator
{
    /** @var SpikeBillable|Model */
    protected $billable;

    protected CreditType $creditType;

    /**
     * @param SpikeBillable|Model $billable
     */
    public function __construct($billable)
    {
        $this->billable = $billable;
    }

    /**
     * @param SpikeBillable|Model $billable
     * @return static
     */
    public static function forBillable($billable): static
    {
        return new self($billable);
    }

    public function setCreditType(CreditType $creditType): static
    {
        $this->creditType = $creditType;

        return $this;
    }

    public function getCreditType(): CreditType
    {
        return $this->creditType ?? CreditType::default();
    }

    public function getBankBeforeTodayCacheKey(): string
    {
        return 'spike::billable:' . $this->billable->spikeCacheIdentifier()
             . ':credit_type:' . $this->getCreditType()->type
             . ':balance_before:' . now()->toDateString();
    }

    public function clearCache(): void
    {
        Cache::forget($this->getBankBeforeTodayCacheKey());
    }

    public function balance(): int
    {
        if (! $this->getCreditType()) {
            throw new \InvalidArgumentException('No credit type set. Cannot calculate the balance.');
        }

        $bank = $this->getBankBeforeToday();

        $newTransactions = CreditTransaction::query()
            ->whereBillable($this->billable)
            ->whereCreditType($this->getCreditType())
            ->where('created_at', '>=', Carbon::today())
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'type', 'credits', 'expires_at', 'created_at']);

        $this->processTransactions($bank, $newTransactions);

        $expiringCredits = array_reduce(
            $bank['expiring'],
            fn ($sum, $expiringBalance) => $sum + $expiringBalance['credits'],
            0
        );

        return $expiringCredits + $bank['credits'];
    }

    protected function getBankBeforeToday()
    {
        if (! $this->getCreditType()) {
            throw new \InvalidArgumentException('No credit type set. Cannot calculate the balance.');
        }

        // Because it's a balance before today, it will no longer change.
        // Thus, we can cache it and return it from cache when needed.

        return Cache::remember($this->getBankBeforeTodayCacheKey(), Carbon::tomorrow(), function () {
            $bank = [
                'credits' => 0,
                'expiring' => [],
            ];

            $transactions = CreditTransaction::query()
                ->whereBillable($this->billable)
                ->whereCreditType($this->getCreditType())
                ->where('created_at', '<', Carbon::today())
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get(['id', 'type', 'credits', 'expires_at', 'created_at', 'updated_at']);

            $this->processTransactions($bank, $transactions);

            return $bank;
        });
    }

    protected function processTransactions(&$bank, $transactions)
    {
        /** @var CreditTransaction $transaction */
        foreach ($transactions as $transaction) {
            // Before we do anything, we should check this transaction's created_at
            // against any stored "banks" of expiring balances. This way we can figure out
            // whether the time has come to expire some of our credit balances.
            $bank['expiring'] = array_filter($bank['expiring'], function ($expiringBalance) use ($transaction) {
                return $transaction->created_at->lte($expiringBalance['date']);
            });

            if ($transaction->credits > 0 && !is_null($transaction->expires_at)) {
                $expiryKey = $transaction->expires_at->timestamp;

                if (!isset($bank['expiring'][$expiryKey])) {
                    $bank['expiring'][$expiryKey] = [
                        'key' => $expiryKey,
                        'date' => $transaction->expires_at->copy(),
                        'credits' => 0,
                    ];
                    ksort($bank['expiring']);
                }

                $bank['expiring'][$expiryKey]['credits'] += $transaction->credits;
            } elseif ($transaction->credits > 0) {
                $bank['credits'] += $transaction->credits;
            } elseif ($transaction->credits < 0) {
                // Usage!
                // Now, let's first figure out whether we have any expiring balances
                $creditsToSpend = abs($transaction->credits);

                while ($creditsToSpend > 0) {
                    $expiringBalance = Arr::first($bank['expiring']);

                    if ($expiringBalance) {
                        $key = $expiringBalance['key'];
                        $spend = min($creditsToSpend, $expiringBalance['credits']);
                        $bank['expiring'][$key]['credits'] -= $spend;
                        $creditsToSpend -= $spend;

                        if ($bank['expiring'][$key]['credits'] <= 0) {
                            unset($bank['expiring'][$key]);
                        }
                    } else {
                        $bank['credits'] -= $creditsToSpend;
                        $creditsToSpend = 0;
                    }
                }
            }
        }

        // Remove any expired banks
        $bank['expiring'] = array_filter($bank['expiring'], function ($expiringBalance) {
            return now()->lte($expiringBalance['date']);
        });
    }
}
