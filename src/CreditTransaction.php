<?php

namespace Opcodes\Spike;

use Carbon\CarbonInterface;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\Contracts\SpikeSubscriptionItem;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Contracts\SpikeBillable;

/**
 * @property-read int $id
 * @property string $type
 * @property string $billable_type
 * @property CreditType $credit_type
 * @property int $billable_id
 * @property int $credits
 * @property int $cart_id
 * @property int $subscription_id
 * @property SpikeSubscription $subscription
 * @property int $subscription_item_id
 * @property SpikeSubscriptionItem $subscriptionItem
 * @property string $notes
 * @property CarbonInterface $expires_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class CreditTransaction extends Model
{
    use HasFactory;

    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_PRODUCT = 'product';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_USAGE = 'usage';

    protected $table = 'spike_credit_transactions';

    protected $fillable = [
        'credit_type',
        'type',
        'billable_type',
        'billable_id',
        'credits',
        'cart_id',
        'cart_item_id',
        'subscription_id',
        'subscription_item_id',
        'expires_at',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($creditTransaction) {
            if (is_null($creditTransaction->billable_id)) {
                $creditTransaction->billable()->associate(Spike::resolve());
            }
        });

        static::saved(function (CreditTransaction $creditTransaction) {
            if ($billable = $creditTransaction->billable) {
                $billable->credits()->type($creditTransaction->credit_type)->clearBalanceCache();
            }
        });
    }

    public function getCreditTypeAttribute(): CreditType
    {
        return isset($this->attributes['credit_type'])
            ? CreditType::make($this->attributes['credit_type'])
            : CreditType::default();
    }

    /**
     * @return MorphTo|SpikeBillable|Model|null
     */
    public function billable(): MorphTo
    {
        return $this->morphTo('billable');
    }

    /**
     * @return BelongsTo|Cart|null
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo|CartItem|null
     */
    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }

    /**
     * @return BelongsTo|SpikeSubscription|null
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Cashier::$subscriptionModel);
    }

    /**
     * @return BelongsTo|SpikeSubscriptionItem|null
     */
    public function subscriptionItem(): BelongsTo
    {
        return $this->belongsTo(Cashier::$subscriptionItemModel);
    }

    /**
     * @throws Exception
     */
    public function prorateTo(CarbonInterface $endDate, $save = true)
    {
        if ($this->expired()) {
            throw new Exception('Expired CreditTransactions can no longer be prorated.');
        }

        $minutesInMonth = $this->created_at->diffInMinutes(
            $this->created_at->copy()->addMonthNoOverflow()
        );
        $minutesUntilEnd = $this->created_at->diffInMinutes($endDate);
        $originalCredits = $this->credits;
        $proratedCredits = round($this->credits * ($minutesUntilEnd / $minutesInMonth));

        $this->credits = min($this->credits, $proratedCredits);
        $this->notes = (!empty($this->notes) ? $this->notes . '. ' : '')
            . __('spike::translations.prorated_from_to_credits', [
                'from' => number_format($originalCredits),
                'to' => number_format($proratedCredits),
            ]);

        if ($save) {
            $this->save();
        }
    }

    public function expire($save = true): void
    {
        if ($this->credits === 0 || $this->expired() || !$this->exists) return;

        $this->expires_at = now();

        if ($save) {
            $this->save();
        }
    }

    public function expireAt($date = null, $save = true): void
    {
        if ($this->expired() || !$this->exists) return;

        $this->expires_at = $date;

        if ($save) {
            $this->save();
        }
    }

    public function expired(): bool
    {
        return !is_null($this->expires_at) && $this->expires_at->lte(now());
    }

    public function isProduct(): bool
    {
        return $this->type === self::TYPE_PRODUCT;
    }

    public function isSubscription(): bool
    {
        return $this->type === self::TYPE_SUBSCRIPTION;
    }

    public function isAdjustment(): bool
    {
        return $this->type === self::TYPE_ADJUSTMENT;
    }

    public function isUsage(): bool
    {
        return $this->type === self::TYPE_USAGE;
    }

    public function getTypeTranslatedAttribute(): string
    {
        return __("spike::translations.transaction_type_{$this->type}");
    }

    public function fullNotes(): string
    {
        $notes = $this->notes;

        if ($this->isUsage()) {
            $notes = ! empty($notes)
                ? $notes
                : __('spike::translations.credits_used_on_date', ['date' => $this->createdAtFormatted()]);

            if ($this->expired()) {
                $notes .= ' ' . __('spike::translations.until_time', ['time' => $this->expires_at->translatedFormat(config('spike.date_formats.transaction_time', 'g:i a'))]);
            }
        }

        return trim($notes ?? '');
    }

    public function createdAtFormatted(): string
    {
        if ($this->created_at->isCurrentYear()) {
            if ($this->isUsage() && ! config('spike.group_credit_spend_daily')) {
                return $this->created_at->translatedFormat(
                    config('spike.date_formats.transaction_usage_datetime', 'F j, H:i:s')
                );
            }

            return $this->created_at->translatedFormat(
                config('spike.date_formats.transaction_date_current_year', 'F j')
            );
        }

        if ($this->isUsage() && ! config('spike.group_credit_spend_daily')) {
            return $this->created_at->translatedFormat(
                config('spike.date_formats.transaction_usage_with_year', 'F j Y, H:i:s')
            );
        }

        return $this->created_at->translatedFormat(
            config('spike.date_formats.transaction_date', 'F j, Y')
        );
    }

    public function expiryDateFormatted(): string
    {
        if ($this->expires_at->isCurrentYear()) {
            return $this->expires_at->translatedFormat(
                config('spike.date_formats.transaction_expiry_current_year', 'F j')
            );
        }

        return $this->expires_at->translatedFormat(
            config('spike.date_formats.transaction_expiry_date', 'F j, Y')
        );
    }

    /**
     * @param Builder $query
     * @param SpikeBillable $billable
     */
    public function scopeWhereBillable(Builder $query, $billable): void
    {
        $query->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', $billable->getKey());
    }

    public function scopeWhereCreditType(Builder $query, CreditType|string $creditType): void
    {
        $query->where('credit_type', CreditType::make($creditType)->type);
    }

    /**
     * @param Builder $query
     * @param SpikeSubscriptionItem $item
     */
    public function scopeForSubscriptionItem(Builder $query, SpikeSubscriptionItem $item): void
    {
        $query->where('subscription_item_id', '=', $item->id);
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where(function ($query) {
            $query->whereNotNull('expires_at')
                ->where('expires_at', '<=', Carbon::now());
        });
    }

    public function scopeNotExpired(Builder $query): void
    {
        $query->where(function ($query) {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', Carbon::now());
        });
    }

    public function scopeCreatedToday(Builder $query): void
    {
        $query->whereDate('created_at', '=', Carbon::today()->toDateString());
    }

    public function scopeOnlyProducts(Builder $query): void
    {
        $query->where('type', self::TYPE_PRODUCT);
    }

    public function scopeOnlySubscriptions(Builder $query): void
    {
        $query->where('type', self::TYPE_SUBSCRIPTION);
    }

    public function scopeOnlyUsages(Builder $query): void
    {
        $query->where('type', self::TYPE_USAGE);
    }

    public function scopeOnlyAdjustments(Builder $query): void
    {
        $query->where('type', self::TYPE_ADJUSTMENT);
    }
}
