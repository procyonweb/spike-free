<?php

use Illuminate\Support\Collection;
use Laravel\Cashier\Coupon;
use Laravel\Cashier\PromotionCode;
use Opcodes\Spike\Contracts\Offer;
use Opcodes\Spike\Facades\PaymentGateway as PaymentGatewayFacade;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Stripe\CouponCodeOffer;
use Opcodes\Spike\Stripe\OfferService;
use Opcodes\Spike\Stripe\PromotionCodeOffer;
use Opcodes\Spike\Stripe\Services\StripeService;
use Opcodes\Spike\SubscriptionPlan;

beforeEach(function () {
    // Mock the StripeService
    $this->stripeService = Mockery::mock(StripeService::class);
    $this->stripeService->shouldReceive('fetchCoupons')
        ->andReturn(collect());
    $this->stripeService->shouldReceive('fetchPromotionCodes')
        ->andReturn(collect());

    // Fake payment gateway
    PaymentGatewayFacade::fake();

    // We need to mock this because of the global cleanup done in Pest.php
    Spike::shouldReceive('clearCustomResolvers')->andReturnNull()->zeroOrMoreTimes();
});

it('gets offers from config', function () {
    // Arrange
    $mockOffer = Mockery::mock(Offer::class);
    $mockOffer->shouldReceive('identifier')->andReturn('test_coupon');

    config(['spike.stripe.cancellation_offers' => [$mockOffer]]);

    // Mock Spike facade
    Spike::shouldReceive('cancellationOffers')
        ->andReturn(collect([$mockOffer]));

    $service = new OfferService($this->stripeService);

    // Act
    $offers = $service->getOffers();

    // Assert
    expect($offers)->toBeInstanceOf(Collection::class)
        ->and($offers)->toHaveCount(1)
        ->and($offers->first())->toBe($mockOffer);
});

it('finds offer by identifier', function () {
    // Arrange
    $mockOffer1 = Mockery::mock(Offer::class);
    $mockOffer1->shouldReceive('identifier')->andReturn('coupon_1');

    $mockOffer2 = Mockery::mock(Offer::class);
    $mockOffer2->shouldReceive('identifier')->andReturn('coupon_2');

    // Mock Spike facade
    Spike::shouldReceive('cancellationOffers')
        ->andReturn(collect([$mockOffer1, $mockOffer2]));

    $service = new OfferService($this->stripeService);

    // Act
    $found = $service->findOffer('coupon_2');
    $notFound = $service->findOffer('non_existent');

    // Assert
    expect($found)->toBe($mockOffer2)
        ->and($notFound)->toBeNull();
});

it('returns available offers for billable', function () {
    // Arrange
    $plan = Mockery::mock(SubscriptionPlan::class);
    $billable = Mockery::mock();
    $billable->shouldReceive('currentSubscriptionPlan')->andReturn($plan);

    $availableOffer = Mockery::mock(Offer::class);
    $availableOffer->shouldReceive('identifier')->andReturn('available_offer');
    $availableOffer->shouldReceive('isAvailableFor')
        ->with($plan, $billable)
        ->andReturn(true);

    $unavailableOffer = Mockery::mock(Offer::class);
    $unavailableOffer->shouldReceive('identifier')->andReturn('unavailable_offer');
    $unavailableOffer->shouldReceive('isAvailableFor')
        ->with($plan, $billable)
        ->andReturn(false);

    // Mock Spike facade
    Spike::shouldReceive('cancellationOffers')
        ->andReturn(collect([$availableOffer, $unavailableOffer]));

    $service = new OfferService($this->stripeService);

    // Act
    $availableOffers = $service->getAvailableOffers($billable);

    // Assert
    expect($availableOffers)->toBeInstanceOf(Collection::class)
        ->and($availableOffers)->toHaveCount(1)
        ->and($availableOffers->first())->toBe($availableOffer);
});

it('returns empty collection when no subscription plan', function () {
    // Arrange
    $billable = Mockery::mock();
    $billable->shouldReceive('currentSubscriptionPlan')->andReturn(null);

    $offer = Mockery::mock(Offer::class);
    // Mock Spike facade
    Spike::shouldReceive('cancellationOffers')
        ->andReturn(collect([$offer]));

    $service = new OfferService($this->stripeService);

    // Act
    $availableOffers = $service->getAvailableOffers($billable);

    // Assert
    expect($availableOffers)->toBeInstanceOf(Collection::class)
        ->and($availableOffers)->toBeEmpty();
});

it('loads coupon data for coupon offers', function () {
    // Arrange
    $coupon = Mockery::mock(Coupon::class);
    $couponOffer = new CouponCodeOffer('test_coupon');

    // Set up the stripe service to return our coupon
    $stripeService = Mockery::mock(StripeService::class);
    $stripeService->shouldReceive('fetchCoupons')
        ->andReturn(collect(['test_coupon' => $coupon]));
    $stripeService->shouldReceive('fetchPromotionCodes')
        ->andReturn(collect());

    // Mock Spike facade
    Spike::shouldReceive('cancellationOffers')
        ->andReturn(collect([$couponOffer]));

    $service = new OfferService($stripeService);

    // Act
    $offers = $service->getOffers();
    $couponFromOffer = $offers->first()->getCoupon();

    // Assert
    expect($couponFromOffer)->toBe($coupon);
});

it('loads promotion code data for promotion code offers', function () {
    // Arrange
    $promotionCode = Mockery::mock(PromotionCode::class);
    $promoOffer = new PromotionCodeOffer('test_promo');

    // Set up the stripe service to return our promotion code
    $stripeService = Mockery::mock(StripeService::class);
    $stripeService->shouldReceive('fetchCoupons')
        ->andReturn(collect());
    $stripeService->shouldReceive('fetchPromotionCodes')
        ->andReturn(collect(['test_promo' => $promotionCode]));

    // Mock Spike facade
    Spike::shouldReceive('cancellationOffers')
        ->andReturn(collect([$promoOffer]));

    $service = new OfferService($stripeService);

    // Act
    $offers = $service->getOffers();
    $promoFromOffer = $offers->first()->getPromotionCode();

    // Assert
    expect($promoFromOffer)->toBe($promotionCode);
});

it('caches offers after first retrieval', function () {
    // Arrange
    $mockOffer = Mockery::mock(Offer::class);
    $mockOffer->shouldReceive('identifier')->andReturn('test_offer');

    // Mock Spike facade - should only be called once
    Spike::shouldReceive('cancellationOffers')->once()
        ->andReturn(collect([$mockOffer]));

    // The service should only call fetchCoupons and fetchPromotionCodes once
    $stripeService = Mockery::mock(StripeService::class);
    $stripeService->shouldReceive('fetchCoupons')->once()->andReturn(collect());
    $stripeService->shouldReceive('fetchPromotionCodes')->once()->andReturn(collect());

    $service = new OfferService($stripeService);

    // Act
    $firstRetrieval = $service->getOffers();
    $secondRetrieval = $service->getOffers();

    // Assert
    expect($firstRetrieval)->toBe($secondRetrieval);
});
