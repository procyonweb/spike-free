<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\SubscriptionPlan;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

beforeEach(function () {
    // For ease of testing, we'll assume 1 credit per day is given for Standard plan,
    // and 2 credits per day for Pro plan
    $credits = intval(now()->diffInDays(now()->addMonthNoOverflow(), true));
    $this->standardPlanMonthlyCredits = $credits;
    $this->proPlanMonthlyCredits = $credits * 2;

    $this->standardPlan = setupMonthlySubscriptionPlan($credits, 100);
    $this->standardPlanYearly = setupYearlySubscriptionPlan($credits, 1000);
    $this->proPlan = setupMonthlySubscriptionPlan($credits * 2, 200);
    $this->proPlanYearly = setupYearlySubscriptionPlan($credits * 2, 2000);
    config(['spike.subscriptions.0.provides_monthly' => [CreditAmount::make($this->freeMonthlyCredits = 10)]]);
    $this->freePlan = \Opcodes\Spike\Facades\Spike::subscriptionPlans()->filter(fn ($plan) => $plan->id === 'free')->first();

    $this->user = createBillable();

    PaymentGateway::fake();
});

it('prorates credit transactions when switching plans days after', function () {
    $this->user->subscribeTo($this->standardPlan);
    $creditTransaction = CreditTransaction::onlySubscriptions()->latest('id')->first();
    expect($creditTransaction->credits)->toBe($this->standardPlanMonthlyCredits)
        ->and($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits);

    // now let's move 7 days forward, which means the previous transaction should
    // be prorated to just 7 credits when we switch to a Pro plan.
    testTime()->addDays($proratedDays = 7);
    $this->user->subscribeTo($this->proPlan);
    $creditTransaction = $creditTransaction->fresh();
    expect($creditTransaction->credits)->toBe($proratedDays)
        ->and($this->user->credits()->balance())->toBe($this->proPlanMonthlyCredits);
    // Because the previous prorated credits have expired and new ones were added
    // from the Pro plan, the user's Credit Balance should be equal to the Pro plan's quota.
});

it('overused credits are subtracted from the new plan after switching', function () {
    $this->user->subscribeTo($this->standardPlan);
    $this->user->credits()->spend(10);
    testTime()->addDays(5);
    $this->user->subscribeTo($this->proPlan);

    // we've used up 10 credits with 5 days, which is 5 more than the prorated quota.
    // This means that when we switch to a different plan, this difference (overusage)
    // should be reflected in the balance.
    expect($this->user->credits()->balance())->toBe($this->proPlanMonthlyCredits - 5);
});

it('can upgrade to a yearly plan which gives just the monthly credits', function () {
    PaymentGateway::billable($this->user)->assertNotSubscribed();

    $this->user->subscribeTo($this->standardPlanYearly);

    PaymentGateway::billable($this->user)->assertSubscribed($this->standardPlanYearly);
    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits);
});

it('can switch to a yearly plan of the same tier, and keep the same credits', function () {
    $this->user->subscribeTo($this->standardPlan);
    PaymentGateway::billable($this->user)->assertSubscribed($this->standardPlan);
    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits)
        ->and(CreditTransaction::count())->toBe(1);

    $this->user->subscribeTo($this->standardPlanYearly);
    PaymentGateway::billable($this->user)->assertSubscribed($this->standardPlanYearly);
    PaymentGateway::billable($this->user)->assertNotSubscribed($this->standardPlan);
    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits)
        ->and(CreditTransaction::count())->toBe(2); // because it was prorated
});

it('when upgrading to year, previous credits are prorated and started anew', function () {
    $this->user->subscribeTo($this->standardPlan);
    PaymentGateway::billable($this->user)->assertSubscribed($this->standardPlan);
    $transaction = CreditTransaction::onlySubscriptions()->latest('id')->first();
    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits)
        ->and($transaction->credits)->toBe($this->standardPlanMonthlyCredits);

    testTime()->addDays($proratedDays = 7);
    $this->user->subscribeTo($this->standardPlanYearly);
    $transaction = $transaction->fresh();
    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits)
        ->and($transaction->credits)->toBe($proratedDays);
});

it('clears balance cache when prorating transactions from previous days', function () {
    // for ease of calculation, let's assume the user gets 1 credit per day.
    $this->user->subscribeTo($this->standardPlan);

    testTime()->addDays(5);
    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits);

    // now that we prorate the transaction, it should clear the cache and re-calculate the balance.
    $this->user->subscribeTo($this->proPlan);
    expect($this->user->credits()->balance())->toBe($this->proPlanMonthlyCredits);
    // because the credits from days ago have expired and are no longer added.
});

it('does not create empty credit transaction when switching subscriptions', function () {
    // First, we subscribe to the Standard plan and spend some credits
    $this->user->subscribeTo($this->standardPlan);
    $this->user->credits()->spend(10);
    testTime()->addDays(15);

    // at this point, we've moved 15 days and used up 10 credits, but today's usage transaction has not been created yet
    expect(CreditTransaction::onlyUsages()->createdToday()->count())->toBe(0);

    // Now, when we switch to a free plan, it should not create any new usage transactions
    $this->user->subscribeTo($this->proPlan);
    expect($this->user->credits()->currentUsageTransaction()->exists)->toBeFalse()
        ->and(CreditTransaction::onlyUsages()->createdToday()->count())->toBe(0);
});

test('subscribing to monthly plan does not set an expiration date on the newly added credits', function () {
    $this->user->subscribeTo($this->standardPlan);
    $transaction = CreditTransaction::onlySubscriptions()->latest('id')->first();

    expect($transaction->credits)->toBe($this->standardPlanMonthlyCredits)
        ->and($transaction->expires_at)->toBeNull();
});

test('subscribing to yearly plan does not set an expiration date on the newly added credits', function () {
    $this->user->subscribeTo($this->standardPlanYearly);
    $transaction = CreditTransaction::onlySubscriptions()->latest('id')->first();

    expect($transaction->credits)->toBe($this->standardPlanMonthlyCredits)
        ->and($transaction->expires_at)->toBeNull();
});

test('upgrading to a yearly plan does not set an expiration date on newly assigned credits', function () {
    $this->user->subscribeTo($this->standardPlan);
    testTime()->freeze(now()->addWeek());

    $this->user->subscribeTo($this->standardPlanYearly);
    $transaction = CreditTransaction::onlySubscriptions()->latest('id')->first();

    expect($transaction->credits)->toBe($this->standardPlanMonthlyCredits)
        ->and($transaction->expires_at)->toBeNull();
});

test('switching plans does not set an expiration date on the newly assigned credits', function () {
    $this->user->subscribeTo($this->standardPlan);
    testTime()->freeze(now()->addWeek());

    $this->user->subscribeTo($this->proPlanYearly);
    $transaction = CreditTransaction::onlySubscriptions()->latest('id')->first();

    expect($transaction->credits)->toBe($this->proPlanMonthlyCredits)
        ->and($transaction->expires_at)->toBeNull();
});

it('can cancel a subscription immediately, prorating the credits', function () {
    $this->user->subscribeTo($this->standardPlan);
    testTime()->freeze(now()->addWeek());

    $this->user->cancelSubscriptionNow();
    $transaction = CreditTransaction::onlySubscriptions()->first();

    expect($transaction->credits)->toBe(7)
        ->and($transaction->expired())->toBeTrue();
});

test('switching to paid plan expires free plan credits', function () {
    CreditTransaction::factory()
        ->forBillable($this->user)
        ->subscription()
        ->create(['credits' => $this->freeMonthlyCredits]);

    expect($this->user->credits()->balance())->toBe($this->freeMonthlyCredits);

    $this->user->subscribeTo($this->standardPlan);

    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits);
});

test('switching from paid to free plan does not expire paid credits', function () {
    $this->user->subscribeTo($this->standardPlan);
    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits);

    $this->user->subscribeTo($this->freePlan);

    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits);
});

test('does not re-provide credits after past_due and return to active', function () {
    // Step 1: User is provided credits when subscription is active
    $this->user->subscribeTo($this->standardPlan);
    $this->artisan('spike:renew-subscription-providables');
    $this->user->credits()->spend($spentFirstMonth = 5);
    $this->user->refresh();
    $initialCredits = $this->user->credits()->balance();
    expect($initialCredits)->toBe($this->standardPlanMonthlyCredits - $spentFirstMonth);

    // Step 2: Update subscription to past_due and renew providables again
    $this->user->subscriptionManager()->getSubscription()->update(['stripe_status' => 'past_due']);
    $this->artisan('spike:renew-subscription-providables');

    $this->user->refresh();
    expect($this->user->credits()->balance())->toBe($this->freeMonthlyCredits - $spentFirstMonth);
    // original paid credit transaction + temporary free plan credit transaction + usage transaction
    $this->assertEquals(3, $existingCreditTransactionCount = CreditTransaction::count());

    // Step 3: User pays, subscription is active again, renewal command runs
    $this->user->subscriptionManager()->getSubscription()->update(['stripe_status' => 'active']);
    $this->artisan('spike:renew-subscription-providables');
    $this->user->refresh();

    // Step 4: Credits should be provided again since their subscription is active again
    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits - $spentFirstMonth);
    $this->assertDatabaseCount(CreditTransaction::class, $existingCreditTransactionCount);

    // Step 5: extra calls should not re-provide credits
    $this->artisan('spike:renew-subscription-providables');
    $this->user->refresh();
    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits - $spentFirstMonth);
    $this->assertDatabaseCount(CreditTransaction::class, $existingCreditTransactionCount);

    // Step 6: move the time forward a month and attempt failure again, to see whether same behaviour occurs
    testTime()->addMonth();
    $this->user->subscriptionManager()->getSubscription()->update(['stripe_status' => 'past_due']);
    $this->artisan('spike:renew-subscription-providables');
    $this->user->refresh();
    expect($this->user->credits()->balance())->toBe($this->freeMonthlyCredits);
    $this->assertDatabaseCount(CreditTransaction::class, $existingCreditTransactionCount + 1);  // free plan credits

    $this->user->credits()->spend($spentSecondMonth = 10);
    $this->assertDatabaseCount(CreditTransaction::class, $existingCreditTransactionCount + 2);  // free plan credits + usage transaction

    // Step 7: move time forward a few days, and change subscription to active again
    testTime()->addDays(5);
    $this->user->subscriptionManager()->getSubscription()->update(['stripe_status' => 'active']);
    $this->artisan('spike:renew-subscription-providables');
    $this->user->refresh();
    // Even though the user has spent the free plan credits already, because they've paid for the subscription a while later,
    // they will get the full subscription credits again because that's when their subscription starts again - after they've paid.
    expect($this->user->credits()->balance())->toBe($this->standardPlanMonthlyCredits);
    // the previous month transactions + free plan credits + usage transaction + new subscription transaction
    $this->assertDatabaseCount(CreditTransaction::class, $existingCreditTransactionCount + 3);
});
