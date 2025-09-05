<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Opcodes\Spike\Paddle\Subscription;
use Opcodes\Spike\Paddle\SubscriptionItem;
use Opcodes\Spike\PaymentProvider;

uses(RefreshDatabase::class);

beforeAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = PaymentProvider::Paddle;
});
afterAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = null;
});
beforeEach(function () {
    config(['cashier.api_key' => 'test_api_key']);
});

it('syncs status from paddle when different from database', function () {
    // Arrange
    $billable = createBillable();
    $subscription = Subscription::factory()
        ->has(SubscriptionItem::factory(['price_id' => 'pri_01', 'status' => 'active', 'quantity' => 1]), 'items')
        ->create([
            'billable_id' => $billable->getKey(),
            'billable_type' => $billable->getMorphClass(),
            'paddle_id' => 'sub_01',
            'status' => 'active', // Database shows active
        ]);

    // Mock the HTTP call to Paddle API
    Http::fake([
        'api.paddle.com/subscriptions/sub_01' => Http::response([
            'data' => [
                'status' => 'canceled', // Paddle shows canceled
                'current_billing_period' => null,
            ],
        ], 200),
    ]);

    // Act
    $renewalDate = $subscription->renewalDate();

    // Assert
    expect($renewalDate)->toBeNull();

    // Verify the status was updated in the database
    $freshSubscription = Subscription::find($subscription->id);
    expect($freshSubscription->status)->toBe('canceled');
});

it('logs warning only for active paddle subscriptions without billing period', function () {
    // Arrange
    Log::shouldReceive('warning')
        ->once()
        ->with(
            'Active Paddle Subscription has no current billing period. This may indicate an issue.',
            \Mockery::on(function ($context) {
                return $context['paddle_id'] === 'sub_03'
                    && $context['status'] === 'active';
            })
        );

    $billable = createBillable();
    $subscription = Subscription::factory()
        ->has(SubscriptionItem::factory(['price_id' => 'pri_01', 'status' => 'active', 'quantity' => 1]), 'items')
        ->create([
            'billable_id' => $billable->getKey(),
            'billable_type' => $billable->getMorphClass(),
            'paddle_id' => 'sub_03',
            'status' => 'active',
        ]);

    // Mock the HTTP call to Paddle API - active but no billing period
    Http::fake([
        'api.paddle.com/subscriptions/sub_03' => Http::response([
            'data' => [
                'status' => 'active',
                'current_billing_period' => null,
            ],
        ], 200),
    ]);

    // Act
    $renewalDate = $subscription->renewalDate();

    // Assert
    expect($renewalDate)->toBeNull();
});

it('returns renewal date for active subscription with billing period', function () {
    // Arrange
    $expectedDate = Carbon::now()->addMonth();
    $billable = createBillable();
    $subscription = Subscription::factory()
        ->has(SubscriptionItem::factory(['price_id' => 'pri_01', 'status' => 'active', 'quantity' => 1]), 'items')
        ->create([
            'billable_id' => $billable->getKey(),
            'billable_type' => $billable->getMorphClass(),
            'paddle_id' => 'sub_04',
            'status' => 'active',
        ]);

    // Mock the HTTP call to Paddle API
    Http::fake([
        'api.paddle.com/subscriptions/sub_04' => Http::response([
            'data' => [
                'status' => 'active',
                'current_billing_period' => [
                    'ends_at' => $expectedDate->toIso8601String(),
                ],
            ],
        ], 200),
    ]);

    // Act
    $renewalDate = $subscription->renewalDate();

    // Assert
    expect($renewalDate)->not->toBeNull();
    expect($renewalDate->format('Y-m-d H:i:s'))->toBe($expectedDate->format('Y-m-d H:i:s'));
});

it('returns null for paused subscription from paddle', function () {
    // Arrange
    $billable = createBillable();
    $subscription = Subscription::factory()
        ->has(SubscriptionItem::factory(['price_id' => 'pri_01', 'status' => 'active', 'quantity' => 1]), 'items')
        ->create([
            'billable_id' => $billable->getKey(),
            'billable_type' => $billable->getMorphClass(),
            'paddle_id' => 'sub_05',
            'status' => 'active', // Database shows active
        ]);

    // Mock the HTTP call to Paddle API to return paused status
    Http::fake([
        'api.paddle.com/subscriptions/sub_05' => Http::response([
            'data' => [
                'status' => 'paused', // Paddle shows paused
                'current_billing_period' => [
                    'ends_at' => Carbon::now()->addMonth()->toIso8601String(),
                ],
            ],
        ], 200),
    ]);

    // Act
    $renewalDate = $subscription->renewalDate();

    // Assert
    expect($renewalDate)->toBeNull();

    // Verify the status was updated in the database
    $freshSubscription = Subscription::find($subscription->id);
    expect($freshSubscription->status)->toBe('paused');
});
