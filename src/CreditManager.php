<?php

namespace Opcodes\Spike;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Opcodes\Spike\Events\CreditBalanceUpdated;
use Opcodes\Spike\Exceptions\NotEnoughBalanceException;
use Opcodes\Spike\Traits\ManagesNegativeBalanceAllowance;
use Opcodes\Spike\Traits\ScopedToBillable;

class CreditManager
{
    use ScopedToBillable;
    use ManagesNegativeBalanceAllowance;

    protected ?CreditType $creditType = null;

    public function type(CreditType|string $creditType): static
    {
        $instance = clone $this;
        $instance->creditType = CreditType::make($creditType);

        if (! $instance->creditType->isValid()) {
            throw new \InvalidArgumentException("Invalid credit type: \"{$instance->creditType->type}\". Please make sure it is configured.");
        }

        return $instance;
    }

    public function getCreditType(): CreditType
    {
        return $this->creditType ?? CreditType::default();
    }

    public function getBillableCacheKey(): string
    {
        $billable = $this->getBillable();

        return "spike::".$billable->spikeCacheIdentifier();
    }

    protected function getBalanceCacheKey(): string
    {
        return $this->getBillableCacheKey().':'.$this->getCreditType()->type.':balance';
    }

    public function balance(): int
    {
        return Cache::driver('array')->remember($this->getBalanceCacheKey(), 1, function () {
            return BalanceAggregator::forBillable($this->getBillable())
                ->setCreditType($this->getCreditType())
                ->balance();
        });
    }

    public function allBalances(): Collection
    {
        return $this->creditTypes()->map(function (CreditType $type) {
            return new CreditBalance(
                $type,
                $this->type($type)->balance(),
            );
        });
    }

    protected function creditTypes(): Collection
    {
        return CreditType::all();
    }

    public function clearBalanceCache(): void
    {
        Cache::driver('array')->forget($this->getBalanceCacheKey());
    }

    public function add(int $credits = 1, $notesOrAttributes = null, $attributes = []): CreditTransaction
    {
        if (is_array($notesOrAttributes)) {
            $attributes = $notesOrAttributes;
            $notes = null;
        } else {
            $notes = $notesOrAttributes;
        }

        $creditType = $this->getCreditType();
        
        // Check if we need to backdate the transaction
        $disableTimestamps = false;
        if (isset($attributes['created_at'])) {
            $disableTimestamps = true;
            // Ensure updated_at is set to match created_at
            if (!isset($attributes['updated_at'])) {
                $attributes['updated_at'] = $attributes['created_at'];
            }
        }
        
        $transaction = CreditTransaction::make(array_merge($attributes, [
            'credit_type' => $creditType->type,
            'type' => CreditTransaction::TYPE_ADJUSTMENT,
            'credits' => $credits,
            'notes' => $notes,
        ]));
        
        // If we're backdating, disable automatic timestamps
        if ($disableTimestamps) {
            $transaction->timestamps = false;
        }
        
        $transaction->billable()->associate($this->getBillable());
        $transaction->save();

        event(new CreditBalanceUpdated(
            $this->getBillable(),
            $this->balance(),
            $transaction,
            $creditType,
        ));

        return $transaction;
    }

    /**
     * @throws NotEnoughBalanceException
     */
    public function remove(int $credits = 1, $notesOrAttributes = null, $attributes = []): CreditTransaction
    {
        if (! $this->canSpend($credits)) {
            throw new NotEnoughBalanceException;
        }

        if (is_array($notesOrAttributes)) {
            $attributes = $notesOrAttributes;
            $notes = null;
        } else {
            $notes = $notesOrAttributes;
        }

        // Check if we need to backdate the transaction
        $disableTimestamps = false;
        if (isset($attributes['created_at'])) {
            $disableTimestamps = true;
            // Ensure updated_at is set to match created_at
            if (!isset($attributes['updated_at'])) {
                $attributes['updated_at'] = $attributes['created_at'];
            }
        }

        $transaction = CreditTransaction::make(array_merge($attributes, [
            'credit_type' => $this->getCreditType()->type,
            'type' => CreditTransaction::TYPE_ADJUSTMENT,
            'credits' => -$credits,
            'notes' => $notes,
        ]));
        
        // If we're backdating, disable automatic timestamps
        if ($disableTimestamps) {
            $transaction->timestamps = false;
        }
        
        $transaction->billable()->associate($this->getBillable());
        $transaction->save();

        event(new CreditBalanceUpdated(
            $this->getBillable(),
            $this->balance(),
            $transaction,
            $this->getCreditType(),
        ));

        return $transaction;
    }

    public function canSpend(int $credits = 1): bool
    {
        if ($this->isNegativeBalanceAllowed()) {
            return true;
        }

        return $this->balance() >= $credits;
    }

    /**
     * Spend available credits.
     *
     * @param int $credits
     * @param string|array|null $notesOrAttributes
     * @param array $attributes
     * @return void
     * @throws NotEnoughBalanceException Thrown when the balance is not sufficient.
     */
    public function spend(int $credits = 1, $notesOrAttributes = null, $attributes = []): void
    {
        if (! $this->canSpend($credits)) {
            throw new NotEnoughBalanceException;
        }

        if (is_array($notesOrAttributes)) {
            $attributes = $notesOrAttributes;
            $notes = null;
        } else {
            $notes = $notesOrAttributes;
        }

        if (! config('spike.group_credit_spend_daily')) {
            $currentUsageTransaction = $this->currentUsageTransaction();
            $currentUsageTransaction->credits -= $credits;
            $currentUsageTransaction->notes = $notes;
            
            // Apply additional attributes if provided
            if (!empty($attributes)) {
                // If created_at is being set, we need to disable automatic timestamps
                if (isset($attributes['created_at'])) {
                    $currentUsageTransaction->timestamps = false;
                    // Make sure updated_at matches created_at for consistency
                    if (!isset($attributes['updated_at'])) {
                        $attributes['updated_at'] = $attributes['created_at'];
                    }
                }
                
                $currentUsageTransaction->fill($attributes);
            }
            
            $currentUsageTransaction->save();
        } else {
            if (isset($notes)) {
                throw new \RuntimeException('Cannot add notes when spending is grouped. Please see `spike.group_credit_spend_daily` config option.');
            }

            if (!empty($attributes)) {
                throw new \RuntimeException('Cannot add attributes when spending is grouped. Please see `spike.group_credit_spend_daily` config option.');
            }

            $currentUsageTransaction = DB::transaction(function () use ($credits) {
                $transaction = $this->currentUsageTransaction();

                if (!$transaction->exists) {
                    $transaction->save();
                }

                // We perform a "relative" change using "decrement" in order to avoid any
                // race conditions in case 2 separate workers are modifying the same
                // credit transaction object.
                CreditTransaction::where('id', $transaction->id)
                    ->lockForUpdate()
                    ->decrement('credits', $credits);

                return tap($transaction, function ($transaction) use ($credits) {
                    $transaction->credits -= $credits;
                });
            }, 50);
        }

        $this->clearBalanceCache();

        event(new CreditBalanceUpdated(
            $this->getBillable(),
            $this->balance(),
            $currentUsageTransaction,
            $this->getCreditType(),
        ));
    }

    public function currentUsageTransaction(): CreditTransaction
    {
        $billable = $this->getBillable();
        $creditType = $this->getCreditType();

        if (config('spike.group_credit_spend_daily')) {
            $creditTransaction = CreditTransaction::query()
                ->whereBillable($billable)
                ->whereCreditType($creditType)
                ->createdToday()
                ->onlyUsages()
                ->notExpired()
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->firstOrNew();
        } else {
            $creditTransaction = CreditTransaction::make();
        }

        if (!$creditTransaction->id) {
            $creditTransaction->credits = 0;
            $creditTransaction->type = CreditTransaction::TYPE_USAGE;
            $creditTransaction->billable()->associate($billable);
            $creditTransaction->credit_type = $creditType->type;
        }

        return $creditTransaction;
    }

    public function currentSubscriptionTransaction(): ?CreditTransaction
    {
        return CreditTransaction::query()
            ->whereBillable($this->getBillable())
            ->whereCreditType($this->getCreditType())
            ->notExpired()
            ->onlySubscriptions()
            ->first();
    }

    public function expireCurrentUsageTransactions(): void
    {
        if (! isset($this->creditType)) {
            $creditTypes = $this->creditTypes();
        } else {
            $creditTypes = collect([$this->creditType]);
        }

        foreach ($creditTypes as $creditType) {
            $this->type($creditType)->currentUsageTransaction()->expire();
        }
    }

    public function clearCache(): void
    {
        if (! isset($this->creditType)) {
            $creditTypes = $this->creditTypes();
        } else {
            $creditTypes = collect([$this->creditType]);
        }

        foreach ($creditTypes as $creditType) {
            BalanceAggregator::forBillable($this->getBillable())
                ->setCreditType($creditType)
                ->clearCache();
        }
    }

    public function clearCustomCallbacks(): void
    {
        self::$negativeBalanceTypeCallbacks = [];
        self::$negativeBalanceCallback = null;
    }

    /**
     * Returns the number of credits spent (type of "usage") on a given date.
     *
     * @param \Carbon\CarbonInterface|string $date
     * @return int
     */
    public function spentOnDate($date): int
    {
        $date = $date instanceof \Carbon\CarbonInterface
            ? $date
            : \Illuminate\Support\Carbon::parse($date);

        return CreditTransaction::query()
            ->whereBillable($this->getBillable())
            ->whereCreditType($this->getCreditType())
            ->whereDate('created_at', $date->toDateString())
            ->onlyUsages()
            ->sum('credits') * -1; // Usage transactions have negative credit values, so we multiply by -1 to get positive number
    }
}
