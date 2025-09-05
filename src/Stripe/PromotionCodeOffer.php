<?php

namespace Opcodes\Spike\Stripe;

use Laravel\Cashier\PromotionCode;
use Opcodes\Spike\Contracts\Offer;
use Opcodes\Spike\SubscriptionPlan;

class PromotionCodeOffer implements Offer
{
    private ?PromotionCode $promotionCode;

    private bool $promotionCodeRetrieved = false;

    /**
     * @param string $promoCodeId Stripe ID of the promotion code (not the user-friendly shortcode)
     * @param string|null $name Short name of the benefit the user will receive. If null, the coupon name will be used.
     * @param string|null $description Longer description of the offer. If null, it will not be shown.
     */
    public function __construct(
        protected string $promoCodeId,
        protected ?string $name = null,
        protected ?string $description = null,
    )
    {
    }

    public static function __set_state(array $data): self
    {
        return new self(
            $data['promoCode'],
            $data['name'],
            $data['description'],
        );
    }

    public function identifier(): string
    {
        return $this->promoCodeId;
    }

    public function name(): string
    {
        return $this->name
            ?? $this->getPromotionCode()?->coupon()?->name
            ?? $this->promoCodeId;
    }

    public function description(): string
    {
        return $this->description ?? '';
    }

    public function isAvailableFor(SubscriptionPlan $plan, mixed $billable): bool
    {
        $coupon = $this->getPromotionCode()?->coupon();

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
        if ($promoCode = $this->getPromotionCode()) {
            app(PaymentGateway::class)
                ->billable($billable)
                ->applyPromotionCode($promoCode->id);
        }
    }

    public function setPromotionCode(?PromotionCode $promotionCode): void
    {
        $this->promotionCode = $promotionCode;
        $this->promotionCodeRetrieved = true;
    }

    public function getPromotionCode(): ?PromotionCode
    {
        if (! isset($this->promotionCode) && ! $this->promotionCodeRetrieved) {
            $this->promotionCode = app(PaymentGateway::class)->findStripePromotionCode($this->promoCodeId);
            $this->promotionCodeRetrieved = true;
        }

        return $this->promotionCode;
    }

    public function view(): ?string
    {
        return 'spike::components.shared.offer-default';
    }
}
