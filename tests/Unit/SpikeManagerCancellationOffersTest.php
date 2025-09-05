<?php

use Illuminate\Support\Collection;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SpikeManager;
use Opcodes\Spike\Stripe\CouponCodeOffer;

beforeEach(function() {
    // Create a fresh SpikeManager instance
    $this->spikeManager = new SpikeManager();
    $this->spikeManager->clearCustomResolvers();
});

it('returns default cancellation offers when no custom resolver is set', function() {
    // Arrange
    $mockOffer = new CouponCodeOffer('test_coupon');
    config(['spike.stripe.cancellation_offers' => [$mockOffer]]);

    // Act
    $offers = $this->spikeManager->cancellationOffers();

    // Assert
    expect($offers)->toBeInstanceOf(Collection::class)
        ->and($offers)->toHaveCount(1)
        ->and($offers[0]->identifier())->toBe('test_coupon');
});

it('uses custom resolver when registered', function() {
    // Arrange
    $defaultOffer = new CouponCodeOffer('default_coupon');
    $customOffer = new CouponCodeOffer('custom_coupon');

    config(['spike.stripe.cancellation_offers' => [$defaultOffer]]);

    // Register custom resolver
    $this->spikeManager->resolveCancellationOffersUsing(function($billable, $defaultOffers) use ($customOffer) {
        return collect([$customOffer]);
    });

    // Act
    $offers = $this->spikeManager->cancellationOffers();

    // Assert
    expect($offers)->toBeInstanceOf(Collection::class)
        ->and($offers)->toHaveCount(1)
        ->and($offers[0]->identifier())->toBe('custom_coupon');
});

it('passes default offers to custom resolver', function() {
    // Arrange
    $defaultOffer = new CouponCodeOffer('default_coupon');
    $customOffer = new CouponCodeOffer('custom_coupon');

    config(['spike.stripe.cancellation_offers' => [$defaultOffer]]);

    // Register custom resolver that adds to default offers
    $this->spikeManager->resolveCancellationOffersUsing(function($billable, $defaultOffers) use ($customOffer) {
        return $defaultOffers->push($customOffer);
    });

    // Act
    $offers = $this->spikeManager->cancellationOffers();

    // Assert
    expect($offers)->toBeInstanceOf(Collection::class)
        ->and($offers)->toHaveCount(2)
        ->and($offers[0]->identifier())->toBe('default_coupon')
        ->and($offers[1]->identifier())->toBe('custom_coupon');
});

it('clears custom resolvers when calling clearCustomResolvers', function() {
    // Arrange
    $defaultOffer = new CouponCodeOffer('default_coupon');
    $customOffer = new CouponCodeOffer('custom_coupon');

    config(['spike.stripe.cancellation_offers' => [$defaultOffer]]);

    // Register custom resolver
    $this->spikeManager->resolveCancellationOffersUsing(function($billable, $defaultOffers) use ($customOffer) {
        return collect([$customOffer]);
    });

    // Verify custom resolver works
    $offersBeforeClear = $this->spikeManager->cancellationOffers();
    expect($offersBeforeClear[0]->identifier())->toBe('custom_coupon');

    // Act
    $this->spikeManager->clearCustomResolvers();

    // Assert - should be back to default
    $offersAfterClear = $this->spikeManager->cancellationOffers();
    expect($offersAfterClear[0]->identifier())->toBe('default_coupon');
});

it('passes correct parameters to the resolver callback', function() {
    // Arrange
    $defaultOffer = new CouponCodeOffer('default_coupon');
    $testBillable = createBillable();
    $this->spikeManager->resolve(fn () => $testBillable);
    config(['spike.stripe.cancellation_offers' => [$defaultOffer]]);

    // Create a mock for checking received parameters
    $callbackCalled = false;
    $receivedBillable = null;
    $receivedDefaultOffers = null;

    // Register custom resolver that captures the parameters
    $this->spikeManager->resolveCancellationOffersUsing(function($billable, $defaultOffers)
        use (&$callbackCalled, &$receivedBillable, &$receivedDefaultOffers) {
        $callbackCalled = true;
        $receivedBillable = $billable;
        $receivedDefaultOffers = $defaultOffers;

        return $defaultOffers; // Just return the default offers
    });

    // Act - call cancellationOffers with our test billable
    $this->spikeManager->cancellationOffers();

    // Assert
    expect($callbackCalled)->toBeTrue()
        ->and($receivedBillable)->toBe($testBillable)
        ->and($receivedDefaultOffers)->toBeInstanceOf(Collection::class)
        ->and($receivedDefaultOffers)->toHaveCount(1)
        ->and($receivedDefaultOffers[0]->identifier())->toBe('default_coupon');

    // Act 2 - if we pass a different billable, that will be used for the callback instead
    $testBillable2 = createBillable();

    $this->spikeManager->cancellationOffers($testBillable2);

    expect($receivedBillable)->toBe($testBillable2);
});
