<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Opcodes\Spike\Events\SubscriptionCancelled;
use Opcodes\Spike\Events\SubscriptionDeactivated;
use Opcodes\Spike\Events\SubscriptionResumed;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\SubscriptionPlan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = createBillable();

    // For ease of testing, we'll assume 1 credit per day is given for Standard plan,
    // and 2 credits per day for Pro plan
    $credits = intval(now()->diffInDays(now()->addMonthNoOverflow(), true));

    $this->standardPlan = setupMonthlySubscriptionPlan($credits, 100);
    $this->standardPlanYearly = setupYearlySubscriptionPlan($credits, 1000);
    $this->proPlan = setupMonthlySubscriptionPlan($credits * 2, 200);
    $this->proPlanYearly = setupYearlySubscriptionPlan($credits * 2, 2000);
    $this->freePlan = SubscriptionPlan::defaultFreePlan();

    PaymentGateway::fake();
});

it('can cancel a subscription', function () {
    $this->user->subscribeTo($this->standardPlan);
    Event::fake([SubscriptionCancelled::class]);

    $this->user->cancelSubscription();

    expect($this->user->subscription()->onGracePeriod())->toBeTrue();
    Event::assertDispatched(SubscriptionCancelled::class, function (SubscriptionCancelled $event) {
        return isset($event->plan)
            && $event->plan->id === $this->standardPlan->id
            && $event->plan->period === $this->standardPlan->period
            && $event->billable->is($this->user);
    });
});

it('can cancel a subscription immediately', function () {
    $this->user->subscribeTo($this->standardPlan);
    Event::fake([SubscriptionDeactivated::class]);

    $this->user->cancelSubscriptionNow();

    expect($this->user->subscription()->onGracePeriod())->toBeFalse();
    Event::assertDispatched(SubscriptionDeactivated::class, function (SubscriptionDeactivated $event) {
        return isset($event->plan)
            && $event->plan->id === $this->standardPlan->id
            && $event->plan->period === $this->standardPlan->period
            && $event->billable->is($this->user);
    });
    Event::assertNotDispatched(SubscriptionCancelled::class);
});

it('can resume a subscription', function () {
    $this->user->subscribeTo($this->standardPlan);
    $this->user->cancelSubscription();
    Event::fake([SubscriptionResumed::class]);

    $this->user->resumeSubscription();

    expect($this->user->subscription()->onGracePeriod())->toBeFalse();
    Event::assertDispatched(SubscriptionResumed::class, function (SubscriptionResumed $event) {
        return isset($event->plan)
            && $event->plan->id === $this->standardPlan->id
            && $event->plan->period === $this->standardPlan->period
            && $event->billable->is($this->user);
    });
});
