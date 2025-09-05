<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Coupon;
use Laravel\Cashier\PromotionCode;
use Opcodes\Spike\Contracts\Offer;
use Opcodes\Spike\Stripe\PaymentGateway;
use Opcodes\Spike\Stripe\PaymentGatewayFake;
use Opcodes\Spike\Stripe\PromotionCodeOffer;
use Opcodes\Spike\SubscriptionPlan;
use Stripe\Coupon as StripeCoupon;
use Stripe\PromotionCode as StripePromotionCode;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(
        PaymentGateway::class,
        new PaymentGatewayFake
    );
});

it('can be created with promotion code ID', function () {
    $offer = new PromotionCodeOffer('promo_123');

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->identifier())->toBe('promo_123')
        ->and($offer->name())->toBe('promo_123')
        ->and($offer->description())->toBe('');
});

it('can be created with name and description', function () {
    $offer = new PromotionCodeOffer('promo_123', 'Special Offer', 'Get 20% off!');

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->identifier())->toBe('promo_123')
        ->and($offer->name())->toBe('Special Offer')
        ->and($offer->description())->toBe('Get 20% off!');
});

it('can be restored from state', function () {
    $state = [
        'promoCode' => 'promo_123',
        'name' => 'Special Offer',
        'description' => 'Get 20% off!'
    ];

    $offer = PromotionCodeOffer::__set_state($state);

    expect($offer)->toBeInstanceOf(PromotionCodeOffer::class)
        ->and($offer->identifier())->toBe('promo_123')
        ->and($offer->name())->toBe('Special Offer')
        ->and($offer->description())->toBe('Get 20% off!');
});

it('uses coupon name when no name is provided', function () {
    $stripeCoupon = StripeCoupon::constructFrom([
        'id' => 'coupon_123',
        'name' => 'Coupon Name',
    ]);
    $promotionCode = new PromotionCode(StripePromotionCode::constructFrom([
        'id' => 'promo_123',
        'coupon' => $stripeCoupon,
    ]));

    /** @var PaymentGatewayFake $paymentGateway */
    $paymentGateway = app(PaymentGateway::class);
    $paymentGateway->setPromotionCode('promo_123', $promotionCode);

    $offer = new PromotionCodeOffer('promo_123');

    expect($offer->name())->toBe('Coupon Name');
});

it('returns promo code ID when no name or coupon is available', function () {
    $offer = new PromotionCodeOffer('promo_123');

    expect($offer->name())->toBe('promo_123');
});

it('checks if offer is available for a plan', function () {
    $plan = new SubscriptionPlan(
        id: 'test_plan',
        name: 'Test Plan',
        payment_provider_price_id: 'price_123',
        price_in_cents: 1000,
        provides_monthly: []
    );

    $stripeCoupon = StripeCoupon::constructFrom([
        'id' => 'coupon_123',
    ]);

    $stripePromotionCode = StripePromotionCode::constructFrom([
        'id' => 'promo_123',
        'coupon' => $stripeCoupon,
    ]);

    $coupon = new Coupon($stripeCoupon);
    $promotionCode = new PromotionCode($stripePromotionCode);

    /** @var PaymentGatewayFake $paymentGateway */
    $paymentGateway = app(PaymentGateway::class);
    $paymentGateway->setPromotionCode('promo_123', $promotionCode);
    $paymentGateway->setValidCouponPrice($coupon, 'price_123');

    $offer = new PromotionCodeOffer('promo_123');

    expect($offer->isAvailableFor($plan, null))->toBeTrue();
});

it('returns false when coupon is not available', function () {
    $plan = new SubscriptionPlan(
        id: 'test_plan',
        name: 'Test Plan',
        payment_provider_price_id: 'price_123',
        price_in_cents: 1000,
        provides_monthly: []
    );

    $offer = new PromotionCodeOffer('promo_123');

    expect($offer->isAvailableFor($plan, null))->toBeFalse();
});

it('applies promotion code to billable', function () {
    $billable = Mockery::mock();

    $promotionCode = new PromotionCode(StripePromotionCode::constructFrom([
        'id' => 'promo_123',
    ]));

    /** @var PaymentGatewayFake $paymentGateway */
    $paymentGateway = app(PaymentGateway::class);
    $paymentGateway->setPromotionCode('promo_123', $promotionCode);

    $offer = new PromotionCodeOffer('promo_123');
    $offer->apply($billable);

    $paymentGateway->assertPromotionCodeApplied('promo_123');
});

it('returns the correct view path', function () {
    $offer = new PromotionCodeOffer('promo_123');

    expect($offer->view())->toBe('spike::components.shared.offer-default');
});

it('can render the view with offer data', function () {
    $offer = new PromotionCodeOffer('promo_123', 'Special Offer', 'Get 20% off!');

    $view = view($offer->view(), ['offer' => [
        'name' => $offer->name(),
        'description' => $offer->description()
    ]]);

    expect($view->render())->toContain('Special Offer')
        ->and($view->render())->toContain('Get 20% off!');
});
