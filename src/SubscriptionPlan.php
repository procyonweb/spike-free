<?php

namespace Opcodes\Spike;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Laravel\Cashier\PromotionCode;
use Opcodes\Spike\Contracts\Providable;
use Opcodes\Spike\Contracts\ProvideHistoryRelatableItemContract;

class SubscriptionPlan implements ProvideHistoryRelatableItemContract, Arrayable
{
    const PERIOD_MONTHLY = 'monthly';
    const PERIOD_YEARLY = 'yearly';

    public bool $current = false;
    public bool $past_due = false;
    public ?CarbonInterface $ends_at = null;
    public ?PromotionCode $promotion_code = null;
    public ?int $price_in_cents_after_discount = null;
    public ?string $discount_repeats = null;
    public int $discount_repeats_months = 0;

    public function __construct(
        public string  $id,
        public string  $name,
        public string  $period = self::PERIOD_MONTHLY,
        public ?string $short_description = null,
        public ?array  $features = [],
        public ?string $payment_provider_price_id = null,
        public ?int    $price_in_cents = null,
        public array   $provides_monthly = [],
        public array   $options = [],
        public bool    $archived = false,
    ) {
        $this->price_in_cents_after_discount = $price_in_cents ?? 0;

        $this->validateProvides();
    }

    public static function fromArray(array $config, $yearly = false): static
    {
        $price_in_cents = $config['price_in_cents_monthly'] ?? 0;

        if ($yearly && isset($config['price_in_cents_yearly'])) {
            $price_in_cents = $config['price_in_cents_yearly'];
        } elseif ($yearly) {
            $price_in_cents = $price_in_cents * 12;
        }

        if (isset($config['provides_monthly'])) {
            $providesMonthly = $config['provides_monthly'];
        } elseif (isset($config['monthly_credits']) && $config['monthly_credits'] > 0) {
            $providesMonthly = [CreditAmount::make($config['monthly_credits'])];
        }

        if ($yearly) {
            $priceId = $config['payment_provider_price_id_yearly'] ?? $config['stripe_price_id_yearly'] ?? $config['id'];
        } else {
            $priceId = $config['payment_provider_price_id_monthly'] ?? $config['stripe_price_id_monthly'] ?? $config['id'];
        }

        return new self(
            id: $config['id'],
            name: $config['name'],
            period: $yearly ? self::PERIOD_YEARLY : self::PERIOD_MONTHLY,
            short_description: $config['short_description'] ?? '',
            features: $config['features'] ?? [],
            payment_provider_price_id: $priceId,
            price_in_cents: $price_in_cents,
            provides_monthly: $providesMonthly ?? [],
            options: $config['options'] ?? [],
            archived: $config['archived'] ?? false,
        );
    }

    /**
     * Returns the default, free subscription plan.
     * This is used when there's no free plan configured in config/spike.php
     */
    public static function defaultFreePlan($yearly = false): static
    {
        return new self(
            id: 'free',
            name: 'Free',
            period: $yearly ? self::PERIOD_YEARLY : self::PERIOD_MONTHLY,
            short_description: '',
            payment_provider_price_id: 'free',
            price_in_cents: 0,
            archived: true,
        );
    }

    public function withPromotionCode(?PromotionCode $promotionCode = null): self
    {
        $this->promotion_code = $promotionCode;

        if ($promotionCode && ($coupon = $promotionCode->coupon())) {
            if ($coupon->isPercentage()) {
                $this->price_in_cents_after_discount = round($this->price_in_cents * (1 - ($coupon->percentOff() / 100)));
            } else {
                $this->price_in_cents_after_discount = max(0, $this->price_in_cents - $coupon->rawAmountOff());
            }

            if ($coupon->duration === 'repeating') {
                $this->discount_repeats = 'monthly';
                $this->discount_repeats_months = $coupon->duration_in_months;
            } elseif ($coupon->duration === 'forever') {
                $this->discount_repeats = null;
                $this->discount_repeats_months = 0;
            } elseif ($coupon->duration === 'once') {
                $this->discount_repeats = 'once';
                $this->discount_repeats_months = 0;
            }
        } else {
            $this->price_in_cents_after_discount = $this->price_in_cents;
            $this->discount_repeats = null;
            $this->discount_repeats_months = 0;
        }

        return $this;
    }

    public function withoutPromotionCode(): self
    {
        return $this->withPromotionCode(null);
    }

    public function hasPromotionCode(): bool
    {
        return $this->promotion_code?->active ?? false;
    }

    public function discountRepeatsMonthly(): bool
    {
        return $this->discount_repeats === 'monthly';
    }

    public function discountRepeatsOnce(): bool
    {
        return $this->discount_repeats === 'once';
    }

    /**
     * Returns the formatted price of this plan, such as "€10,00"
     */
    public function priceFormatted(): string
    {
        return Utils::formatAmount($this->price_in_cents);
    }

    public function monthlyPriceFormatted(): string
    {
        if ($this->isMonthly()) {
            return $this->priceFormatted();
        }

        return Utils::formatAmount(round($this->price_in_cents / 12, 2, PHP_ROUND_HALF_DOWN));
    }

    public function priceAfterDiscountFormatted(): string
    {
        return Utils::formatAmount($this->price_in_cents_after_discount);
    }

    public function monthlyPriceAfterDiscountFormatted(): string
    {
        if ($this->isMonthly()) {
            return $this->priceAfterDiscountFormatted();
        }

        return Utils::formatAmount(round($this->price_in_cents_after_discount / 12, 2, PHP_ROUND_HALF_DOWN));
    }

    /** Check whether the plan is monthly */
    public function isMonthly(): bool
    {
        return $this->period === self::PERIOD_MONTHLY;
    }

    /** Check whether the plan is yearly */
    public function isYearly(): bool
    {
        return $this->period === self::PERIOD_YEARLY;
    }

    /** Check whether the plan is free */
    public function isFree(): bool
    {
        return $this->price_in_cents <= 0;
    }

    /** Check whether the plan is paid */
    public function isPaid(): bool
    {
        return $this->price_in_cents > 0;
    }

    public function isActive(): bool
    {
        return ! $this->archived;
    }

    /**
     * @deprecated Use isActive() instead
     */
    public function active(): bool
    {
        return $this->isActive();
    }

    /** Check whether the plan is the current plan of the resolved billable */
    public function isCurrent(): bool
    {
        return $this->current;
    }

    public function isPastDue(): bool
    {
        return $this->past_due;
    }

    /** Check whether the plan has been cancelled for the resolved billable */
    public function isCancelled(): bool
    {
        return !is_null($this->ends_at);
    }

    private function validateProvides()
    {
        foreach ($this->provides_monthly as $provides) {
            if (! is_object($provides)) {
                throw new \InvalidArgumentException('Every element inside the "provides_monthly" array must be an object.');
            }

            if (! in_array(Providable::class, class_implements($provides))) {
                throw new \InvalidArgumentException('The class ' . get_class($provides) . ' must implement the ' . Providable::class . ' interface.');
            }
        }
    }

    public function provideHistoryId(): string
    {
        return $this->id . ':' . $this->period;
    }

    public function getMorphClass()
    {
        return get_class($this);
    }

    public function provideHistoryType(): string
    {
        return get_class($this);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'period' => $this->period,
            'short_description' => $this->short_description,
            'features' => $this->features,
            'payment_provider_price_id' => $this->payment_provider_price_id,
            'price_in_cents' => $this->price_in_cents,
            'provides_monthly' => $this->provides_monthly,
            'options' => $this->options,
            'archived' => $this->archived,
            'current' => $this->current,
            'past_due' => $this->past_due,
            'ends_at' => $this->ends_at,
            'promotion_code' => $this->promotion_code,
            'price_in_cents_after_discount' => $this->price_in_cents_after_discount,
            'discount_repeats' => $this->discount_repeats,
            'discount_repeats_months' => $this->discount_repeats_months,
        ];
    }
}
