<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Opcodes\Spike\Events\SubscriptionActivated;
use Opcodes\Spike\Events\SubscriptionDeactivated;
use Opcodes\Spike\Exceptions\InvalidSubscriptionPlanException;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\SubscriptionPlan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = createBillable();

    // For ease of testing, we'll assume 1 credit per day is given for Standard plan,
    // and 2 credits per day for Pro plan
    $credits = intval(now()->diffInDays(now()->addMonthNoOverflow(), true));
    $this->standardMonthlyCredits = $credits;
    $this->proMonthlyCredits = $credits * 2;

    $this->standardPlan = setupMonthlySubscriptionPlan($this->standardMonthlyCredits, 100, 'price_standard_monthly');
    $this->standardPlanYearly = setupYearlySubscriptionPlan($this->standardMonthlyCredits, 1000, 'price_standard_yearly');
    $this->proPlan = setupMonthlySubscriptionPlan($this->proMonthlyCredits, 200, 'price_pro_monthly');
    $this->proPlanYearly = setupYearlySubscriptionPlan($this->proMonthlyCredits, 2000, 'price_pro_yearly');
    $this->freePlan = SubscriptionPlan::defaultFreePlan();

    PaymentGateway::fake();
});

it('can subscribe to a standard plan', function () {
    PaymentGateway::billable($this->user)->assertNotSubscribed();

    $this->user->subscribeTo($this->standardPlan);

    PaymentGateway::billable($this->user)->assertSubscribed($this->standardPlan);
    expect($this->user->credits()->balance())->toBe($this->standardMonthlyCredits);
});

it('can subscribe a given billable to a standard plan', function () {
    $this->user->subscribeTo($this->proPlan);
    $anotherUser = createBillable();
    PaymentGateway::billable($anotherUser)->assertNotSubscribed();

    $anotherUser->subscribeTo($this->standardPlan);

    PaymentGateway::billable($anotherUser)->assertSubscribed($this->standardPlan);
    PaymentGateway::billable($this->user)->assertSubscribed($this->proPlan);
});

it('can switch to a different subscription plan', function () {
    $this->user->subscribeTo($this->standardPlan);
    PaymentGateway::billable($this->user)->assertSubscribed($this->standardPlan);
    expect($this->user->credits()->balance())->toBe($this->standardMonthlyCredits);

    $this->user->subscribeTo($this->proPlan);
    PaymentGateway::billable($this->user)->assertSubscribed($this->proPlan);
    PaymentGateway::assertNotSubscribed($this->standardPlan);
    expect($this->user->credits()->balance())->toBe($this->proMonthlyCredits);
});

it('can unsubscribe by switching to a free subscription', function () {
    $this->user->subscribeTo($this->standardPlan);
    PaymentGateway::billable($this->user)->assertSubscribed($this->standardPlan);
    expect($this->user->credits()->balance())->toBe($this->standardMonthlyCredits);

    // When we switch to a free plan, the previous plan is still active
    // until the end of its period.
    $this->user->subscribeTo($this->freePlan);
    PaymentGateway::billable($this->user)->assertSubscribed($this->standardPlan);
    PaymentGateway::billable($this->user)->billable($this->user)->assertSubscriptionCancelled();
});

it('fires a SubscriptionActivated event when subscribing', function () {
    Event::fake([SubscriptionActivated::class]);
    $this->user->subscribeTo($this->standardPlan);

    Event::assertDispatched(SubscriptionActivated::class, function (SubscriptionActivated $event) {
        return isset($event->plan)
            && $event->plan->id === $this->standardPlan->id
            && $event->plan->period === $this->standardPlan->period
            && $event->billable->is($this->user);
    });
});

it('fires a SubscriptionDeactivated event when swapping subscription plans', function () {
    $this->user->subscribeTo($this->standardPlan);
    Event::fake([SubscriptionDeactivated::class, SubscriptionActivated::class]);

    $this->user->subscribeTo($this->proPlan);

    Event::assertDispatched(SubscriptionDeactivated::class, function (SubscriptionDeactivated $event) {
        return isset($event->plan)
            && $event->plan->id === $this->standardPlan->id
            && $event->plan->period === $this->standardPlan->period
            && $event->billable->is($this->user);
    });
    Event::assertDispatched(SubscriptionActivated::class, function (SubscriptionActivated $event) {
        return isset($event->plan)
            && $event->plan->id === $this->proPlan->id
            && $event->plan->period === $this->proPlan->period
            && $event->billable->is($this->user);
    });
});

it('fires the correct events when switching to yearly subscription', function () {
    $this->user->subscribeTo($this->standardPlan);
    Event::fake([SubscriptionDeactivated::class, SubscriptionActivated::class]);

    $this->user->subscribeTo($this->standardPlanYearly);

    Event::assertDispatched(SubscriptionDeactivated::class, function (SubscriptionDeactivated $event) {
        return isset($event->plan)
            && $event->plan->id === $this->standardPlan->id
            && $event->plan->period === $this->standardPlan->period
            && $event->billable->is($this->user);
    });
    Event::assertDispatched(SubscriptionActivated::class, function (SubscriptionActivated $event) {
        return isset($event->plan)
            && $event->plan->id === $this->standardPlanYearly->id
            && $event->plan->period === $this->standardPlanYearly->period
            && $event->billable->is($this->user);
    });
});

it('throws an exception if the subscription plan has an empty payment_provider_price_id', function () {
    $this->standardPlan->payment_provider_price_id = '';

    try {
        $this->user->subscribeTo($this->standardPlan);
    } catch (Exception $exception) {
        expect($exception)->toBeInstanceOf(InvalidSubscriptionPlanException::class)
            ->getMessage()->toBe('The subscription plan does not have a valid "payment_provider_price_id" value.');
        return;
    }

    $this->fail('Expected the InvalidSubscriptionPlanException exception to be thrown, but it was not.');
});
