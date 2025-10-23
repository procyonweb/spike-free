<?php

namespace Opcodes\Spike\Mollie;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription as CashierSubscription;
use Opcodes\Spike\Contracts\SpikeSubscription;
use Opcodes\Spike\Facades\PaymentGateway as PaymentGatewayFacade;

class Subscription extends CashierSubscription implements SpikeSubscription
{
    use HasFactory;

    protected $table = 'mollie_subscriptions';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'renews_at' => 'datetime',
        'cycle_started_at' => 'datetime',
        'cycle_ends_at' => 'datetime',
    ];

    public function getBillable()
    {
        return $this->owner;
    }

    public function getForeignKey()
    {
        return 'mollie_subscription_id';
    }

    public function getPriceId(): string
    {
        return $this->plan;
    }

    public function isPastDue(): bool
    {
        // Mollie doesn't have a past_due status, consider active with failed payments
        return false;
    }

    public function getPromotionCodeId(): ?string
    {
        return $this->promotion_code_id;
    }

    /**
     * Mollie Cashier doesn't use subscription items like Stripe/Paddle.
     * This returns a collection with a single item representing the subscription plan.
     *
     * @return Collection
     */
    public function items()
    {
        // Create a pseudo-item for compatibility with Spike's expectations
        return new Collection([
            (object) [
                'plan' => $this->plan,
                'quantity' => $this->quantity ?? 1,
            ]
        ]);
    }

    public function hasPaymentCard(): bool
    {
        return ! empty($this->mollie_subscription_id);
    }

    public function hasPromotionCode(): bool
    {
        return !is_null($this->promotionCode());
    }

    public function promotionCode()
    {
        if ($this->promotion_code_id) {
            $cacheKey = 'spike.mollie_subscription_'.$this->id.'_promotion_code';
            $promotionCode = Cache::driver('array')->remember($cacheKey, 10, function () {
                // TODO: Implement Mollie promotion code retrieval if supported
                return null;
            });

            if (is_null($promotionCode)) {
                $this->promotion_code_id = null;
                $this->save();
                Cache::driver('array')->forget($cacheKey);
            }

            return $promotionCode;
        }

        return null;
    }

    public function renewalDate(): ?CarbonInterface
    {
        if ($this->onTrial()) {
            return $this->trial_ends_at;
        }

        if ($this->cycle_ends_at) {
            return $this->cycle_ends_at;
        }

        return $this->renews_at;
    }

    public function hasPriceId(string $priceId): bool
    {
        return $this->plan === $priceId;
    }

    public function stopCancelation()
    {
        return $this->resume();
    }
}
