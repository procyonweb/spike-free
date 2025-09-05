<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\ProvideHistory;
use Opcodes\Spike\Stripe\SubscriptionItem;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

it('can create a provide history item', function () {
    $billable = createBillable();
    $providable = CreditAmount::make(500);

    $item = ProvideHistory::create([
        'billable_id' => $billable->getKey(),
        'billable_type' => $billable->getMorphClass(),
        'related_item_type' => CartItem::class,
        'related_item_id' => 1,
        'providable_key' => $providable->key(),
        'providable_data' => serialize($providable),
        'provided_at' => $providedAt = now(),
        'failed_at' => null,
        'exception' => null,
    ]);

    $this->assertDatabaseHas(ProvideHistory::class, [
        'id' => $item->getKey(),
        'billable_id' => $billable->getKey(),
        'billable_type' => $billable->getMorphClass(),
        'related_item_type' => CartItem::class,
        'related_item_id' => 1,
        'providable_key' => $providable->key(),
        'providable_data' => serialize($providable),
        'provided_at' => $providedAt,
        'failed_at' => null,
        'exception' => null,
    ]);
});

it('can create a successful provide history for cart item', function () {
    $billable = createBillable();
    $providable = CreditAmount::make(500);
    $cartItem = CartItem::factory()->create();

    $item = ProvideHistory::createSuccessfulProvide(
        $cartItem, $providable, $billable
    );

    $this->assertDatabaseHas(ProvideHistory::class, [
        'id' => $item->getKey(),

        'billable_id' => $billable->getKey(),
        'billable_type' => $billable->getMorphClass(),

        'related_item_id' => $cartItem->getKey(),
        'related_item_type' => $cartItem->getMorphClass(),

        'providable_key' => $providable->key(),
        'providable_data' => serialize($providable),

        'provided_at' => now(),
        'failed_at' => null,
        'exception' => null,
    ]);
});

it('can create a failed provide history for cart item', function () {
    $billable = createBillable();
    $providable = CreditAmount::make(500);
    $cartItem = CartItem::factory()->create();

    $item = ProvideHistory::createFailedProvide(
        $cartItem, $providable, $billable, $exception = new \Exception('Something went wrong')
    );

    $this->assertDatabaseHas(ProvideHistory::class, [
        'id' => $item->getKey(),

        'billable_id' => $billable->getKey(),
        'billable_type' => $billable->getMorphClass(),

        'related_item_id' => $cartItem->getKey(),
        'related_item_type' => $cartItem->getMorphClass(),

        'providable_key' => $providable->key(),
        'providable_data' => serialize($providable),

        'provided_at' => null,
        'failed_at' => now(),
        'exception' => (string) $exception,
    ]);
});

it('can create a successful provide history for subscription item', function () {
    $billable = createBillable();
    $providable = CreditAmount::make(500);
    $subscriptionItem = SubscriptionItem::factory()->create();

    $item = ProvideHistory::createSuccessfulProvide(
        $subscriptionItem, $providable, $billable
    );

    $this->assertDatabaseHas(ProvideHistory::class, [
        'id' => $item->getKey(),

        'billable_id' => $billable->getKey(),
        'billable_type' => $billable->getMorphClass(),

        'related_item_id' => $subscriptionItem->provideHistoryId(),
        'related_item_type' => $subscriptionItem->provideHistoryType(),

        'providable_key' => $providable->key(),
        'providable_data' => serialize($providable),

        'provided_at' => now(),
        'failed_at' => null,
        'exception' => null,
    ]);
});

it('can create a failed provide history for subscription item', function () {
    $billable = createBillable();
    $providable = CreditAmount::make(500);
    $subscriptionItem = SubscriptionItem::factory()->create();

    $item = ProvideHistory::createFailedProvide(
        $subscriptionItem, $providable, $billable, $exception = new \Exception('Something went wrong')
    );

    $this->assertDatabaseHas(ProvideHistory::class, [
        'id' => $item->getKey(),

        'billable_id' => $billable->getKey(),
        'billable_type' => $billable->getMorphClass(),

        'related_item_id' => $subscriptionItem->provideHistoryId(),
        'related_item_type' => $subscriptionItem->provideHistoryType(),

        'providable_key' => $providable->key(),
        'providable_data' => serialize($providable),

        'provided_at' => null,
        'failed_at' => now(),
        'exception' => (string) $exception,
    ]);
});

it('can check whether it has already provided for a cart item', function () {
    $billable = createBillable();
    $providable = CreditAmount::make(500);
    $cartItem = CartItem::factory()->create();

    expect(ProvideHistory::hasProvided($cartItem, $providable, $billable))
        ->toBeFalse();

    ProvideHistory::createSuccessfulProvide(
        $cartItem, $providable, $billable
    );

    expect(ProvideHistory::hasProvided($cartItem, $providable, $billable))
        ->toBeTrue();
});

it('can check whether it has already provided for a subscription item', function () {
    $billable = createBillable();
    $providable = CreditAmount::make(500);
    $subscriptionItem = SubscriptionItem::factory()->create();

    expect(ProvideHistory::hasProvidedMonthly($subscriptionItem, $providable, $billable))
        ->toBeFalse();

    ProvideHistory::createSuccessfulProvide(
        $subscriptionItem, $providable, $billable
    );

    expect(ProvideHistory::hasProvidedMonthly($subscriptionItem, $providable, $billable))
        ->toBeTrue();

    // the day before renewal, it's still true
    testTime()->addMonthNoOverflow()->subDay();
    expect(ProvideHistory::hasProvidedMonthly($subscriptionItem, $providable, $billable))
        ->toBeTrue();

    // after one full month has passed, it should be false again
    testTime()->addDay();

    expect(ProvideHistory::hasProvidedMonthly($subscriptionItem, $providable, $billable))
        ->toBeFalse();
});

it('can check whether it has already provided for a subscription item after price ID has changed', function () {
    $billable = createBillable();
    $providable = CreditAmount::make(500);
    $subscriptionItem = SubscriptionItem::factory()->create();

    expect(ProvideHistory::hasProvidedMonthly($subscriptionItem, $providable, $billable))
        ->toBeFalse();

    ProvideHistory::createSuccessfulProvide(
        $subscriptionItem, $providable, $billable
    );

    expect(ProvideHistory::hasProvidedMonthly($subscriptionItem, $providable, $billable))
        ->toBeTrue();

    // now, let's change the price ID of the subscription item
    $subscriptionItem->update(['stripe_price' => 'new-price-id']);

    expect(ProvideHistory::hasProvidedMonthly($subscriptionItem, $providable, $billable))
        ->toBeFalse();
});

it('can check whether it has already provided for a yearly subscription item', function () {
    $billable = createBillable();
    $providable = CreditAmount::make(500);
    $subscriptionItem = SubscriptionItem::factory()->create();
    PaymentGateway::fake();
    PaymentGateway::setRenewalDate(now()->addYear());
    testTime()->addDay(); // we move a day forward so that the renewal date is not today.
    $nextMonthlyRenewal = $billable->subscriptionMonthlyRenewalDate()->copy();

    expect(ProvideHistory::hasProvidedMonthly($subscriptionItem, $providable, $billable))
        ->toBeFalse();

    ProvideHistory::createSuccessfulProvide(
        $subscriptionItem, $providable, $billable
    );

    expect(ProvideHistory::hasProvidedMonthly($subscriptionItem, $providable, $billable))
        ->toBeTrue();

    // the day before renewal, it's still true
    testTime()->freeze($nextMonthlyRenewal->copy()->subDay());
    expect(ProvideHistory::hasProvidedMonthly($subscriptionItem, $providable, $billable))
        ->toBeTrue();

    // after one full month has passed, it should be false again
    testTime()->freeze($nextMonthlyRenewal->copy());

    expect(ProvideHistory::hasProvidedMonthly($subscriptionItem, $providable, $billable))
        ->toBeFalse();
});

it('can check whether it has already provided for a subscription plan without sub item', function () {
    $billable = createBillable();
    $providable = CreditAmount::make(500);
    $plan = Spike::findSubscriptionPlan('standard');
    PaymentGateway::fake();
    PaymentGateway::partialMock()->shouldReceive('getRenewalDate')
        ->andReturn(Carbon::now()->addDays($renewalAfterDays = 10));

    expect(ProvideHistory::hasProvidedMonthly($plan, $providable, $billable))
        ->toBeFalse();

    ProvideHistory::createSuccessfulProvide($plan, $providable, $billable);

    expect(ProvideHistory::hasProvidedMonthly($plan, $providable, $billable))
        ->toBeTrue();

    // the day before renewal, it should still be true
    testTime()->addDays($renewalAfterDays - 1);
    expect(ProvideHistory::hasProvidedMonthly($plan, $providable, $billable))
        ->toBeTrue();

    // on the renewal day, it should be false again
    testTime()->addDay();
    expect(ProvideHistory::hasProvidedMonthly($plan, $providable, $billable))
        ->toBeFalse();

    ProvideHistory::createSuccessfulProvide($plan, $providable, $billable);
    expect(ProvideHistory::hasProvidedMonthly($plan, $providable, $billable))
        ->toBeTrue();
});
