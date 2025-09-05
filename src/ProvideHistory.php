<?php

namespace Opcodes\Spike;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\Contracts\Providable;
use Opcodes\Spike\Contracts\ProvideHistoryRelatableItemContract;
use Opcodes\Spike\Contracts\SpikeBillable;

class ProvideHistory extends Model
{
    protected $table = 'spike_provide_history';

    protected $fillable = [
        'billable_id',
        'billable_type',
        'related_item_id',
        'related_item_type',
        'providable_key',
        'providable_data',
        'provided_at',
        'failed_at',
        'exception',
    ];

    /**
     * @param ProvideHistoryRelatableItemContract $relatedItem
     * @param Providable $providable
     * @param SpikeBillable|Model $billable
     * @return static
     */
    public static function createSuccessfulProvide(ProvideHistoryRelatableItemContract $relatedItem, Providable $providable, Model $billable, ?CarbonInterface $providedAt = null): static
    {
        return static::create([
            'billable_id' => $billable->getKey(),
            'billable_type' => $billable->getMorphClass(),

            'related_item_id' => $relatedItem->provideHistoryId(),
            'related_item_type' => $relatedItem->provideHistoryType(),

            'providable_key' => $providable->key(),
            'providable_data' => serialize($providable),

            'provided_at' => $providedAt ?? now(),
        ]);
    }

    /**
     * @param ProvideHistoryRelatableItemContract $relatedItem
     * @param Providable $providable
     * @param SpikeBillable|Model $billable
     * @param \Throwable $throwable
     * @return static
     */
    public static function createFailedProvide(ProvideHistoryRelatableItemContract $relatedItem, Providable $providable, Model $billable, \Throwable $throwable): static
    {
        return static::create([
            'billable_id' => $billable->getKey(),
            'billable_type' => $billable->getMorphClass(),

            'related_item_id' => $relatedItem->provideHistoryId(),
            'related_item_type' => $relatedItem->provideHistoryType(),

            'providable_key' => $providable->key(),
            'providable_data' => serialize($providable),

            'failed_at' => now(),
            'exception' => (string) $throwable,
        ]);
    }

    public static function hasProvided(ProvideHistoryRelatableItemContract $relatedItem, Providable $providable, Model $billable): bool
    {
        return static::query()
            ->where('billable_id', $billable->getKey())
            ->where('billable_type', $billable->getMorphClass())
            ->where('related_item_id', $relatedItem->provideHistoryId())
            ->where('related_item_type', $relatedItem->provideHistoryType())
            ->where('providable_key', $providable->key())
            ->whereNotNull('provided_at')
            ->exists();
    }

    /**
     * @param ProvideHistoryRelatableItemContract $relatedItem
     * @param Providable $providable
     * @param SpikeBillable|Model $billable
     * @return bool
     */
    public static function hasProvidedMonthly(ProvideHistoryRelatableItemContract $relatedItem, Providable $providable, Model $billable): bool
    {
        $renewalDate = tap(
            $billable->subscriptionMonthlyRenewalDate()?->copy() ?? now(),
            fn ($date) => $date->isAfter(now()->endOfDay()) ? $date->subMonthNoOverflow() : $date,
        );

        return static::query()
            ->where('billable_id', $billable->getKey())
            ->where('billable_type', $billable->getMorphClass())
            ->where('related_item_id', $relatedItem->provideHistoryId())
            ->where('related_item_type', $relatedItem->provideHistoryType())
            ->where('providable_key', $providable->key())
            ->whereNotNull('provided_at')
            ->whereDate('provided_at', '>=', $renewalDate)
            ->exists();
    }
}
