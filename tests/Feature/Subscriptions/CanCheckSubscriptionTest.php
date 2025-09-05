<?php /** @noinspection ALL */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SubscriptionPlan;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var \Opcodes\Spike\Contracts\SpikeBillable user */
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

afterEach(function () {
    \Laravel\Cashier\Cashier::$deactivatePastDue = true;
});

it('can check whether subscribed', function () {
    expect($this->user->isSubscribed())->toBeFalse();

    $this->user->subscribeTo($this->standardPlan);

    expect($this->user->isSubscribed())->toBeTrue();
});

it('does not treat past_due as subscribed', function (bool $shouldDeactivatePastDue, bool $shouldBeSubscribed) {
    \Laravel\Cashier\Cashier::$deactivatePastDue = $shouldDeactivatePastDue;
    /** @var \Opcodes\Spike\Contracts\SpikeSubscription $subscription */
    $subscription = $this->user->subscribeTo($this->standardPlan);
    $subscription->update(['stripe_status' => \Stripe\Subscription::STATUS_PAST_DUE]);

    expect($this->user->fresh()->isSubscribed())->toBe($shouldBeSubscribed);
})->with([
    'deactivate past due' => [true, false],
    'allow past due' => [false, true],
]);

it('should return subscription even if past due', function () {
    \Laravel\Cashier\Cashier::$deactivatePastDue = true;
    /** @var \Opcodes\Spike\Contracts\SpikeSubscription $subscription */
    $subscription = $this->user->subscribeTo($this->standardPlan);
    $subscription->update(['stripe_status' => \Stripe\Subscription::STATUS_PAST_DUE]);

    expect($this->user->fresh()->getSubscription())->not->toBeNull();
});

it('can check whether subscribed to a particular plan', function () {
    expect($this->user->isSubscribed($this->standardPlan))->toBeFalse();

    $this->user->subscribeTo($this->standardPlan);

    expect($this->user->isSubscribed($this->standardPlan))->toBeTrue()
        ->and($this->user->isSubscribed($this->proPlan))->toBeFalse()

    // alternate methods
        ->and($this->user->isSubscribedTo($this->standardPlan))->toBeTrue()
        ->and($this->user->isSubscribedTo($this->proPlan))->toBeFalse();
});

it('can get the current subscription plan', function () {
    expect($this->user->currentSubscriptionPlan())
        ->id->toBe($this->freePlan->id);

    $this->user->subscribeTo($this->standardPlan);

    expect($this->user->currentSubscriptionPlan())
        ->not->toBeNull()
        ->id->toBe($this->standardPlan->id)
        ->payment_provider_price_id->toBe($this->standardPlan->payment_provider_price_id)
        ->period->toBe($this->standardPlan->period);
});

it('can get an archived subscription plan', function () {
    $this->user->subscribeTo($this->standardPlan);

    expect($this->user->currentSubscriptionPlan())
        ->not->toBeNull()
        ->id->toBe($this->standardPlan->id);

    makeSubscriptionPlanArchived($this->standardPlan);

    // can still get the individual plan globally, even if archived
    expect(Spike::findSubscriptionPlan($this->standardPlan->id))
        ->not->toBeNull();
    // and the subscriptions list will contain it still, because it is the current plan
    expect(Spike::subscriptionPlans($this->user)->contains(fn ($plan) => $plan->id === $this->standardPlan->id))
        ->toBeTrue();
    // but will not be returned when getting the global list of subscription plans
    expect(Spike::subscriptionPlans()->contains(fn ($plan) => $plan->id === $this->standardPlan->id))
        ->toBeFalse();

    // but if we switch to a different plan, then the archived plan is no longer current
    $this->user->subscribeTo($this->proPlan);

    // we can still get the individual plan globally, even if archived
    expect(Spike::findSubscriptionPlan($this->standardPlan->id))
        ->not->toBeNull();
    // but the subscriptions list will no longer contain it, because it is no longer the current plan
    expect(Spike::subscriptionPlans($this->user)->contains(fn ($plan) => $plan->id === $this->standardPlan->id))
        ->toBeFalse();
    // and will not be returned when getting the global list of subscription plans
    expect(Spike::subscriptionPlans()->contains(fn ($plan) => $plan->id === $this->standardPlan->id))
        ->toBeFalse();
});

it('resolves the billable if not provided for currentSubscriptionPlan', function () {
    Spike::resolve(fn () => $this->user);

    $this->user->subscribeTo($this->standardPlan);

    expect(Spike::currentSubscriptionPlan()?->id)->toBe($this->standardPlan->id);
});

it('does not return archived free plan even if currently on that plan', function () {
    expect(Spike::subscriptionPlans($this->user)->contains(fn ($plan) => $plan->id === $this->freePlan->id))
        ->toBeTrue();
    expect(Spike::currentSubscriptionPlan($this->user)?->id)->toBe($this->freePlan->id);

    makeSubscriptionPlanArchived($this->freePlan);

    expect(Spike::subscriptionPlans($this->user)->contains(fn ($plan) => $plan->id === $this->freePlan->id))
        ->toBeFalse();
    // but we can still get the current plan even if it was archived
    expect(Spike::currentSubscriptionPlan($this->user)?->id)->toBe($this->freePlan->id);
});

function makeSubscriptionPlanArchived(SubscriptionPlan $plan)
{
    $subConfiguration = config('spike.subscriptions');

    foreach ($subConfiguration as $index => $subConfig) {
        if ($subConfig['id'] === $plan->id) {
            $subConfiguration[$index]['archived'] = true;
        }
    }

    config(['spike.subscriptions' => $subConfiguration]);
}
