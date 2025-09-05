<?php

namespace Opcodes\Spike\Stripe;

use Laravel\Cashier\Coupon;
use Opcodes\Spike\Contracts\Offer;
use Opcodes\Spike\SubscriptionPlan;

class CouponCodeOffer implements Offer
{
    private ?Coupon $coupon;

    private bool $couponRetrieved = false;

    /**
     * @param string $couponId Stripe ID of the coupon code
     * @param string|null $name Short name of the benefit the user will receive. If null, the coupon name will be used.
     * @param string|null $description Longer description of the offer. If null, it will not be shown.
     */
    public function __construct(
        protected string $couponId,
        protected ?string $name = null,
        protected ?string $description = null,
    )
    {
    }

    public static function __set_state(array $data): self
    {
        return new self(
            $data['couponId'],
            $data['name'],
            $data['description'],
        );
    }

    public function identifier(): string
    {
        return $this->couponId;
    }

    public function name(): string
    {
        return $this->name
            ?? $this->getCoupon()?->name
            ?? $this->couponId;
    }

    public function description(): string
    {
        return $this->description ?? '';
    }

    public function isAvailableFor(SubscriptionPlan $plan, mixed $billable): bool
    {
        $coupon = $this->getCoupon();

        if (! $coupon) {
            return false;
        }

        return app(PaymentGateway::class)->isCouponValidForPrice(
            $coupon,
            $plan->payment_provider_price_id
        );
    }

    public function apply(mixed $billable): void
    {
        if ($coupon = $this->getCoupon()) {
            app(PaymentGateway::class)
                ->billable($billable)
                ->applyCoupon($coupon->id);
        }
    }

    public function setCoupon(?Coupon $coupon): void
    {
        $this->coupon = $coupon;
        $this->couponRetrieved = true;
    }

    public function getCoupon(): ?Coupon
    {
        if (! isset($this->coupon) && ! $this->couponRetrieved) {
            $this->coupon = app(PaymentGateway::class)->findStripeCouponCode($this->couponId);
            $this->couponRetrieved = true;
        }

        return $this->coupon;
    }

    public function view(): ?string
    {
        return 'spike::components.shared.offer-default';
    }
}
