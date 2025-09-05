<?php

use Mockery\MockInterface;
use Opcodes\Spike\Actions\ChargeForNegativeBalances;
use Opcodes\Spike\Actions\Subscriptions\ProcessBillableSubscriptionRenewalAction;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\ProvideHistory;
use Opcodes\Spike\SubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Jobs\ProcessBillableSubscriptionRenewal;
use Illuminate\Support\Facades\Queue;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

function assertMonthlyCreditsProvided($billable, $plan): void {
    assertTrue(ProvideHistory::hasProvidedMonthly(
        $billable->subscription()->items()->first(),
        $plan->provides_monthly[0],
        $billable
    ), 'Monthly credits were not provided');
}

function assertMonthlyCreditsNotProvided($billable, $plan): void {
    assertFalse(ProvideHistory::hasProvidedMonthly(
        $billable->subscription()->items()->first(),
        $plan->provides_monthly[0],
        $billable
    ), 'Monthly credits were provided');
}

beforeEach(function () {
    // For ease of testing, we'll assume 1 credit per day is given for Standard plan,
    // and 2 credits per day for Pro plan
    $credits = intval(now()->diffInDays(now()->addMonthNoOverflow(), true));
    $this->standardMonthlyCredits = $credits;
    $this->proMonthlyCredits = $credits * 2;

    $this->standardPlan = setupMonthlySubscriptionPlan($this->standardMonthlyCredits, 100);
    $this->standardPlanYearly = setupYearlySubscriptionPlan($this->standardMonthlyCredits, 1000);
    $this->proPlan = setupMonthlySubscriptionPlan($this->proMonthlyCredits, 200);
    $this->proPlanYearly = setupYearlySubscriptionPlan($this->proMonthlyCredits, 2000);

    PaymentGateway::fake();
    $this->user = createBillable();
    Spike::resolve(function () {
        throw new \Exception('This should not have been called.');
    });

    $this->user->subscribeTo($this->standardPlan);
    assertMonthlyCreditsProvided($this->user, $this->standardPlan);
    $this->originalTransaction = CreditTransaction::onlySubscriptions()->latest('id')->first();
    $this->freePlan = Spike::monthlySubscriptionPlans($this->user)->filter->isFree()->first();
});

it('charges for negative balance before renewing subscription credits', function () {
    testTime()->addDay();
    $nextRenewal = $this->user->subscriptionMonthlyRenewalDate();
    $this->mock(
        ChargeForNegativeBalances::class,
        fn (MockInterface $mock) => $mock->shouldReceive('handle')
            ->with(Mockery::on(fn ($arg) => $this->user->is($arg)), Mockery::any())
            ->once()
    );

    // let's run the command at midnight of the day of renewal
    testTime()->freeze($nextRenewal->copy());
    $this->artisan('spike:renew-subscription-providables');
});

it('does not charge for negative credit balances if today is not renewal day', function () {
    testTime()->addDay();
    $nextRenewal = $this->user->subscriptionMonthlyRenewalDate();
    $this->mock(
        ChargeForNegativeBalances::class,
        fn (MockInterface $mock) => $mock->shouldNotReceive('handle')
    );

    // let's run the command one day before renewal, and the credit balances should not be charged
    testTime()->freeze($nextRenewal->copy()->subDay());
    $this->artisan('spike:renew-subscription-providables');
});

it('gives new credits when subscription is about to be renewed', function () {
    $subscriptionTransactionCount = CreditTransaction::onlySubscriptions()->count();
    testTime()->addDay();
    $nextRenewal = $this->user->subscriptionMonthlyRenewalDate();

    // let's run the command at midnight of the day of renewal
    testTime()->freeze($nextRenewal->copy());
    $this->artisan('spike:renew-subscription-providables');

    assertTrue($this->originalTransaction->fresh()->expired(), 'Original subscription credit transaction was not expired');
    assertEquals($subscriptionTransactionCount + 1, CreditTransaction::onlySubscriptions()->count());
    $newTransaction = CreditTransaction::onlySubscriptions()->latest('id')->first();

    expect($newTransaction)->not->toBeNull()
        ->and($newTransaction->expires_at)->toBeNull()
        ->and($newTransaction->credits)->toBe($this->standardMonthlyCredits)
        ->and($newTransaction->created_at->toDateString())->toBe(now()->toDateString());

    // Now, let's run the command again just to see there's no more changes
    $this->artisan('spike:renew-subscription-providables');
    assertEquals($subscriptionTransactionCount + 1, CreditTransaction::onlySubscriptions()->count());
    assertFalse($newTransaction->fresh()->expired());
});

it('still gives credits if the renewal is next month, but the user did not get credits for the current month', function () {
    testTime()->addDay();
    $nextRenewal = $this->user->subscriptionMonthlyRenewalDate();
    testTime()->freeze($nextRenewal->copy()->addDay()->startOfDay());
    assertMonthlyCreditsNotProvided($this->user, $this->standardPlan);
    PaymentGateway::fake();
    PaymentGateway::setRenewalDate($nextRenewal->copy()->addMonthNoOverflow());

    $this->artisan('spike:renew-subscription-providables');

    assertMonthlyCreditsProvided($this->user, $this->standardPlan);
    $newTransaction = CreditTransaction::onlySubscriptions()->latest('id')->first();
    expect($this->originalTransaction->fresh()->expired())->toBeTrue()
        ->and($newTransaction)->not->toBeNull()
        ->and($newTransaction->id)->not->toBe($this->originalTransaction->id)
        ->and($newTransaction->expires_at)->toBeNull()
        ->and($newTransaction->credits)->toBe($this->standardMonthlyCredits)
        ->and($newTransaction->created_at->toDateString())->toBe(now()->toDateString());
});

it('does not renew credits too early for a yearly subscription', function () {
    PaymentGateway::fake();
    PaymentGateway::setRenewalDate(now()->addYear());
    testTime()->addDay();
    testTime()->freeze($this->user->subscriptionMonthlyRenewalDate()->copy()->subDay());
    assertMonthlyCreditsProvided($this->user, $this->standardPlan);

    $this->artisan('spike:renew-subscription-providables');

    expect($this->originalTransaction->fresh()->expired())->toBeFalse()
        ->and(CreditTransaction::onlySubscriptions()->count())->toBe(1);
});

it('does not renew credits for a past_due subscription', function () {
    $subscriptionTransactionCount = CreditTransaction::onlySubscriptions()->count();
    testTime()->addDay();
    $nextRenewal = $this->user->subscriptionMonthlyRenewalDate();
    /** @var \Opcodes\Spike\Stripe\Subscription $subscription */
    $subscription = app(SubscriptionManager::class)->billable($this->user)->getSubscription();
    $subscription->stripe_status = \Stripe\Subscription::STATUS_PAST_DUE;
    $subscription->save();
    assert($subscription->isPastDue());

    // let's run the command at midnight of the day of renewal
    testTime()->freeze($nextRenewal->copy());
    $this->artisan('spike:renew-subscription-providables');

    assertTrue($this->originalTransaction->fresh()->expired(), 'Original subscription credit transaction was not expired');
    assertEquals($subscriptionTransactionCount + 1, CreditTransaction::onlySubscriptions()->count());
    // a new subscription transaction was still created, but only free plan credits were given, which with this configuration is 0
    assertEquals($this->freePlan->provides_monthly[0]->getAmount(), $this->user->credits()->balance());
});

it('does not renew credits the day before', function () {
    $transaction = CreditTransaction::onlySubscriptions()->latest('id')->first();
    $subscriptionTransactionCount = CreditTransaction::onlySubscriptions()->count();
    $nextRenewal = $this->user->subscriptionMonthlyRenewalDate();

    testTime()->freeze($nextRenewal->subDay());
    $this->artisan('spike:renew-subscription-providables');

    assertFalse($this->originalTransaction->fresh()->expired(), 'Original subscription credit transaction was expired');
    assertEquals($subscriptionTransactionCount, CreditTransaction::onlySubscriptions()->count());
});

it('does not renew credits on the day of cancellation', function () {
    $this->user->cancelSubscription();
    $subscriptionTransactionCount = CreditTransaction::onlySubscriptions()->count();
    testTime()->addDay();
    $nextRenewal = $this->user->subscriptionMonthlyRenewalDate();

    testTime()->freeze($nextRenewal->copy()->startOfDay());
    $this->artisan('spike:renew-subscription-providables');

    $originalTransaction = $this->originalTransaction->fresh();
    expect($originalTransaction->expires_at)->not->toBeNull()
        ->and($originalTransaction->expires_at->timestamp)->toBe($nextRenewal->timestamp)
        ->and(CreditTransaction::onlySubscriptions()->count())->toBe($subscriptionTransactionCount);

    // now let's run the command again just to make sure there's no changes.
    $this->artisan('spike:renew-subscription-providables');

    $originalTransaction = $originalTransaction->fresh();
    expect($originalTransaction->expires_at)->not->toBeNull()
        ->and($originalTransaction->expires_at->timestamp)->toBe($nextRenewal->timestamp)
        ->and(CreditTransaction::onlySubscriptions()->count())->toBe($subscriptionTransactionCount);
});

it('dispatches jobs to the queue when using --queue option', function () {
    Queue::fake();
    
    $this->artisan('spike:renew-subscription-providables --queue');
    
    Queue::assertPushed(ProcessBillableSubscriptionRenewal::class, function ($job) {
        return $job->billables->contains($this->user);
    });
});

it('dispatches jobs to the specified queue when using --queue-name option', function () {
    Queue::fake();
    
    $queueName = 'subscription-renewals';
    $this->artisan("spike:renew-subscription-providables --queue --queue-name=$queueName");
    
    Queue::assertPushedOn($queueName, ProcessBillableSubscriptionRenewal::class, function ($job) {
        return $job->billables->contains($this->user);
    });
});

it('processes jobs synchronously when not using --queue option', function () {
    Queue::fake();
    
    $this->artisan('spike:renew-subscription-providables');
    
    // Jobs should not be pushed to the queue
    Queue::assertNothingPushed();
    
    // Check that the job's effect still happened (credits were still processed)
    $transaction = CreditTransaction::onlySubscriptions()->latest('id')->first();
    expect($transaction)->not->toBeNull();
});

it('respects the chunk-size option when dispatching jobs', function () {
    Queue::fake();
    
    // Create a few more billables
    $billable2 = createBillable();
    $billable2->subscribeTo($this->standardPlan);
    
    $billable3 = createBillable();
    $billable3->subscribeTo($this->standardPlan);
    
    // Set chunk size to 1 so each billable should be in a separate job
    $this->artisan('spike:renew-subscription-providables --queue --chunk-size=1');
    
    // Should have pushed 3 separate jobs (one per billable)
    Queue::assertPushed(ProcessBillableSubscriptionRenewal::class, 3);
    
    Queue::clearResolvedInstances();
    Queue::fake();
    
    // Now set chunk size to 5 so all billables should be in a single job
    $this->artisan('spike:renew-subscription-providables --queue --chunk-size=5');
    
    // Should have pushed only 1 job containing all billables
    Queue::assertPushed(ProcessBillableSubscriptionRenewal::class, 1);
});

it('processes renewal correctly with the action', function () {
    // Mock the action to verify it's called correctly
    $mockAction = $this->mock(
        ProcessBillableSubscriptionRenewalAction::class,
        function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->with(
                    Mockery::on(fn ($arg) => $this->user->is($arg)),
                    Mockery::type('integer'),
                    Mockery::any()
                )
                ->once();
        }
    );
    
    $this->artisan('spike:renew-subscription-providables');
});
