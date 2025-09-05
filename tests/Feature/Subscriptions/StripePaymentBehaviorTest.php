<?php

namespace Opcodes\Spike\Tests\Feature\Subscriptions;

use Mockery;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SpikeManager;
use Opcodes\Spike\Stripe\PaymentGateway;
use Opcodes\Spike\Stripe\Subscription;

// Helper functions need to be defined outside closures
function createTestablePaymentGateway(bool $allowIncompleteUpdates): PaymentGateway
{
    // Create a testable instance of PaymentGateway
    return new class($allowIncompleteUpdates) extends PaymentGateway {
        private $allowIncompleteUpdates;

        public function __construct(bool $allowIncompleteUpdates)
        {
            $this->allowIncompleteUpdates = $allowIncompleteUpdates;
        }

        public function getBillable()
        {
            // Mock billable object with necessary methods
            $billable = Mockery::mock(SpikeBillable::class);
            $billable->shouldReceive('stripePromotionCode')->andReturn(null);
            return $billable;
        }

        // Use the real implementation method for testing
        public function stripeAllowIncompleteSubscriptionUpdates(): bool
        {
            return $this->allowIncompleteUpdates;
        }

        // Simplify by disabling other dependencies
        public function stripePersistDiscountsWhenSwitchingPlans(): bool
        {
            return false;
        }
    };
}

function createMockSubscription(): Subscription
{
    $subscription = Mockery::mock(Subscription::class)->makePartial();
    $subscription->shouldReceive('getPromotionCodeId')->andReturn(null);
    return $subscription;
}

// Set up the test environment before each test
beforeEach(function () {
    // Replace the Spike facade with our mock to avoid clearCustomResolvers() call
    $mock = Mockery::mock(SpikeManager::class);
    Spike::swap($mock);

    // Add expectations needed by our tests
    $mock->shouldReceive('clearCustomResolvers')->zeroOrMoreTimes();
    $mock->shouldReceive('stripeAllowIncompleteSubscriptionUpdates')->andReturn(false)->byDefault();
});

afterEach(function () {
    Mockery::close();
});

/**
 * These tests verify that the actual PaymentGateway implementation
 * correctly adds or skips the payment_behavior option based on configuration.
 */
it('adds payment_behavior option when incomplete updates are disabled', function () {
    // Set up the mock to return false for stripeAllowIncompleteSubscriptionUpdates
    Spike::shouldReceive('stripeAllowIncompleteSubscriptionUpdates')
        ->andReturn(false);

    // Create the actual PaymentGateway with appropriate mocking
    $gateway = createTestablePaymentGateway(false);

    // Create a mock subscription object
    $subscription = createMockSubscription();

    // Call the actual implementation method
    $options = $gateway->buildSubscriptionUpdateOptions($subscription);

    // Verify the error_if_incomplete option is included
    expect($options)->toHaveKey('payment_behavior');
    expect($options['payment_behavior'])->toBe('error_if_incomplete');
});

it('does not add payment_behavior option when incomplete updates are enabled', function () {
    // Set up the mock to return true for stripeAllowIncompleteSubscriptionUpdates
    Spike::shouldReceive('stripeAllowIncompleteSubscriptionUpdates')
        ->andReturn(true);

    // Create the actual PaymentGateway with appropriate mocking
    $gateway = createTestablePaymentGateway(true);

    // Create a mock subscription object
    $subscription = createMockSubscription();

    // Call the actual implementation method
    $options = $gateway->buildSubscriptionUpdateOptions($subscription);

    // Verify the error_if_incomplete option is NOT included
    expect($options)->not->toHaveKey('payment_behavior');
});
