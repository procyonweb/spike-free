<?php

namespace Opcodes\Spike\Stripe;

use Illuminate\Support\Collection;
use Opcodes\Spike\Contracts\Offer;
use Opcodes\Spike\Stripe\Services\StripeService;

class OfferService
{
    protected Collection $coupons;

    protected Collection $promotionCodes;

    protected Collection $offers;

    public function __construct(protected ?StripeService $stripeService = null)
    {
        $this->stripeService = $stripeService ?? new StripeService();
    }

    public function getOffers(): Collection
    {
        if (! isset($this->offers)) {
            $this->offers = \Opcodes\Spike\Facades\Spike::cancellationOffers();

            $this->loadCoupons($this->offers);
            $this->loadPromotionCodes($this->offers);
        }

        return $this->offers;
    }

    public function findOffer(string $identifier): ?Offer
    {
        return $this->getOffers()
            ->first(fn (Offer $offer) => $offer->identifier() === $identifier);
    }

    /**
     * @param mixed|\Opcodes\Spike\Contracts\SpikeBillable $billable
     * @return Collection
     */
    public function getAvailableOffers(mixed $billable): Collection
    {
        $plan = $billable->currentSubscriptionPlan();

        if (! $plan) {
            return collect();
        }

        return $this->getOffers()
            ->filter(fn (Offer $offer) => $offer->isAvailableFor($plan, $billable))
            ->values();
    }

    protected function loadCoupons(Collection $offers): void
    {
        if (! isset($this->coupons)) {
            $this->coupons = $this->stripeService->fetchCoupons();
        }

        $offers->each(function (Offer $offer) {
            if ($offer instanceof CouponCodeOffer) {
                $coupon = $this->coupons->get($offer->identifier());
                $offer->setCoupon($coupon);
            }
        });
    }

    protected function loadPromotionCodes(Collection $offers): void
    {
        if (! isset($this->promotionCodes)) {
            $this->promotionCodes = $this->stripeService->fetchPromotionCodes();
        }

        $offers->each(function (Offer $offer) {
            if ($offer instanceof PromotionCodeOffer) {
                $promotionCode = $this->promotionCodes->get($offer->identifier());
                $offer->setPromotionCode($promotionCode);
            }
        });
    }
}
