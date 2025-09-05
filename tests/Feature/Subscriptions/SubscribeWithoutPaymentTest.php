<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Stripe\Subscription;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

it('can subscribe without payment details', function () {
    $billable = createBillable();
    $credits = intval(now()->diffInDays(now()->addMonthNoOverflow(), true));    // 1 credit per day
    $plan = setupMonthlySubscriptionPlan($credits, 200);

    $billable->subscribeWithoutPaymentTo($plan);

    expect($billable->credits()->balance())->toBe($credits)
        ->and($billable->isSubscribedTo($plan))->toBeTrue();

    $billable->credits()->spend(10);

    // now let's move to the next month and run the credit renewal command
    testTime()->addMonthNoOverflow()->addSecond();
    $currentTransaction = $billable->credits()->currentSubscriptionTransaction();
    $expectedRenewalDate = $billable->subscription()->created_at->copy()->addMonthsNoOverflow(2)->toDateTimeString();
    $this->artisan('spike:renew-subscription-providables');

    $billable->unsetRelation('subscriptions');
    expect($billable->credits()->balance())->toBe($credits)
        ->and($billable->credits()->currentSubscriptionTransaction()->id)->not->toBe($currentTransaction->id)
        ->and($billable->subscription()->renewalDate()->toDateTimeString())->toBe($expectedRenewalDate);

    // calling the command the second time on the same day will not renew the credits
    $currentTransaction = $billable->credits()->currentSubscriptionTransaction();
    $this->artisan('spike:renew-subscription-providables');

    $billable->unsetRelation('subscriptions');
    expect($billable->credits()->balance())->toBe($credits)
        ->and($billable->credits()->currentSubscriptionTransaction()->id)->toBe($currentTransaction->id)
        ->and($billable->subscription()->renewalDate()->toDateTimeString())->toBe($expectedRenewalDate);
});

it('can switch to a different plan without payment details', function () {
    $billable = createBillable();
    $credits = intval(now()->diffInDays(now()->addMonthNoOverflow(), true));    // 1 credit per day
    $plan = setupMonthlySubscriptionPlan($credits, 200);
    $newPlan = setupMonthlySubscriptionPlan($credits * 2, 500);
    $billable->subscribeWithoutPaymentTo($plan);
    expect($billable->isSubscribedTo($plan))->toBeTrue();

    // now let's switch
    $billable->subscribeWithoutPaymentTo($newPlan);

    expect($billable->isSubscribedTo($newPlan))->toBeTrue()
        ->and($billable->subscriptions()->count())->toBe(1);
});

it('can switch to a free plan whilst the original plan was without payment card', function () {
    $billable = createBillable();
    $credits = intval(now()->diffInDays(now()->addMonthNoOverflow(), true));    // 1 credit per day
    $plan = setupMonthlySubscriptionPlan($credits, 200);
    $freePlan = \Opcodes\Spike\SubscriptionPlan::defaultFreePlan();

    $billable->subscribeWithoutPaymentTo($plan);
    expect($billable->isSubscribedTo($plan))->toBeTrue();

    // now downgrade to the free plan
    $billable->subscribeTo($freePlan);

    expect($billable->isSubscribedTo($plan))->toBeTrue()
        ->and($billable->subscription()->onGracePeriod())->toBeTrue()
        ->and($billable->subscription()->ends_at->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString())
        ->and($billable->subscriptions()->count())->toBe(1);
});

it('can witch to another paid plan whilst original plan was without payment card', function () {
    $billable = createBillable();
    $credits = intval(now()->diffInDays(now()->addMonthNoOverflow(), true));    // 1 credit per day
    $plan = setupMonthlySubscriptionPlan($credits, 200);
    $newPlan = setupMonthlySubscriptionPlan($credits * 2, 500);
    $billable->subscribeWithoutPaymentTo($plan);
    expect($billable->isSubscribedTo($plan))->toBeTrue();

    // now let's switch
    try {
        $billable->subscribeTo($newPlan);
        $this->fail('Expected an actual call to Stripe API');
    } catch (\Stripe\Exception\AuthenticationException $e) {
        $trace = $e->getTrace();

        // if the trace contains a call to the method "createSubscription" then the test succeeded
        $this->assertTrue(collect($trace)->contains(fn ($item) => $item['function'] === 'createSubscription'));
    }
});

test('system can handle more than one non-Stripe subscription', function () {
    $plan = setupMonthlySubscriptionPlan(30, 200);
    $billable1 = createBillable();
    $billable2 = createBillable();

    $billable1->subscribeWithoutPaymentTo($plan);
    $billable2->subscribeWithoutPaymentTo($plan);

    $this->assertDatabaseCount(Subscription::class, 2);
    expect(Subscription::where('stripe_id', '')->count())->toBe(2);
});
