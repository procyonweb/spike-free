<?php

use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SubscriptionPlan;

// Subscriptions
test('Spike::subscriptionPlans() is empty when no subscriptions were configured', function () {
    config(['spike.subscriptions' => null]);

    expect(Spike::subscriptionPlans())->toBeEmpty()
        ->and(Spike::subscriptionPlansAvailable())->toBeFalse();
});

test('Spike::subscriptionPlans() returns a configured subscription plan', function () {
    config(['spike.subscriptions' => [
        $standardConfig = [
            'id' => 'standard',
            'name' => 'Standard',
            'short_description' => 'For small businesses with continuous use',
            'payment_provider_price_id_monthly' => 'standard_price_id_monthly',
            'payment_provider_price_id_yearly' => 'standard_price_id_yearly',
            'price_in_cents_monthly' => 10_00,
            'price_in_cents_yearly' => 100_00,
            'provides_monthly' => [
                CreditAmount::make(5_000)
            ],
            'options' => [
                'foo' => 'bar',
            ],
        ],
    ]]);

    // when there's no free plan configured, it creates a default free plan automatically.
    expect(Spike::subscriptionPlans())->toHaveCount(2)
        ->and(Spike::monthlySubscriptionPlans())->toHaveCount(1)
        ->and(Spike::monthlySubscriptionPlans()->filter->isPaid()->first())
            ->toBeInstanceOf(SubscriptionPlan::class)
            ->id->toBe($standardConfig['id'])
            ->name->toBe($standardConfig['name'])
            ->short_description->toBe($standardConfig['short_description'])
            ->payment_provider_price_id->toBe($standardConfig['payment_provider_price_id_monthly'])
            ->price_in_cents->toBe($standardConfig['price_in_cents_monthly'])
            ->provides_monthly->toBe($standardConfig['provides_monthly'])
            ->options->toBe($standardConfig['options'])
        ->and(Spike::yearlySubscriptionPlans())->toHaveCount(1)
        ->and(Spike::yearlySubscriptionPlans()->filter->isPaid()->first())
            ->toBeInstanceOf(SubscriptionPlan::class)
            ->id->toBe($standardConfig['id'])
            ->name->toBe($standardConfig['name'])
            ->short_description->toBe($standardConfig['short_description'])
            ->payment_provider_price_id->toBe($standardConfig['payment_provider_price_id_yearly'])
            ->price_in_cents->toBe($standardConfig['price_in_cents_yearly'])
            ->provides_monthly->toBe($standardConfig['provides_monthly'])
            ->options->toBe($standardConfig['options']);
});

test('Spike::subscriptionPlans() contains a free plan automatically if not already there', function () {
    config(['spike.subscriptions' => null]);

    // if there's no subscription plans available, then there's no need for a free plan.
    expect(Spike::subscriptionPlans(includeArchived: true))->toBeEmpty();

    config(['spike.subscriptions' => [
        [
            'id' => 'standard',
            'name' => 'Standard',
            'short_description' => 'For small businesses with continuous use',
            'payment_provider_price_id_monthly' => 'standard_price_id_monthly',
            'price_in_cents_monthly' => 10_00,
            'provides_monthly' => [
                CreditAmount::make(5_000)
            ],
        ],
    ]]);

    $defaultMonthlyPlan = SubscriptionPlan::defaultFreePlan();
    expect(Spike::subscriptionPlans(includeArchived: true))->toHaveCount(4)
        ->and(Spike::monthlySubscriptionPlans(includeArchived: true)->filter->isFree()->first())
            ->toBeInstanceOf(SubscriptionPlan::class)
            ->id->toBe($defaultMonthlyPlan->id)
            ->name->toBe($defaultMonthlyPlan->name)
            ->payment_provider_price_id->toBe($defaultMonthlyPlan->payment_provider_price_id)
            ->provides_monthly->toBe($defaultMonthlyPlan->provides_monthly)
            ->archived->toBeTrue();
});

test('subscription plans take monthly price when yearly price is not given', function () {
    config(['spike.subscriptions' => [
        $standardConfig = [
            'id' => 'standard',
            'name' => 'Standard',
            'short_description' => 'For small businesses with continuous use',
            'payment_provider_price_id_monthly' => 'standard_price_id_monthly',
            'price_in_cents_monthly' => 10_00,
        ]
    ]]);

    $plans = Spike::subscriptionPlans();

    /** @var SubscriptionPlan $subscriptionYearlyPlan */
    $subscriptionYearlyPlan = $plans->filter(function (SubscriptionPlan $plan) {
        return $plan->isYearly() && $plan->id === 'standard';
    })->first();

    expect($subscriptionYearlyPlan->price_in_cents)->toBe($standardConfig['price_in_cents_monthly'] * 12);
});

test('Spike::subscriptionPlans() does not overwrite a free plan with a default', function () {
    config(['spike.subscriptions' => [
        $standardConfig = [
            'id' => 'standard',
            'name' => 'Standard',
            'short_description' => 'For small businesses with continuous use',
            'payment_provider_price_id_monthly' => 'standard_price_id_monthly',
            'price_in_cents_monthly' => 10_00,
            'provides_monthly' => [
                CreditAmount::make(20_000)
            ],
        ],
        $freeConfig = [
            'id' => 'free',
            'name' => 'Different name',
            'short_description' => 'For small businesses with continuous use',
            'provides_monthly' => [
                CreditAmount::make($freeCredits = 6_000)
            ],
        ],
    ]]);

    $plans = Spike::subscriptionPlans();

    expect($plans)->toHaveCount(4)
        ->and($plans->filter->isFree()->first())
        ->toBeInstanceOf(SubscriptionPlan::class)
        ->id->toBe($freeConfig['id'])
        ->name->toBe($freeConfig['name'])
        ->provides_monthly->toBe($freeConfig['provides_monthly'])
        ->price_in_cents->toBe(0);
});

test('Spike::resolveSubscriptionPlansUsing() allows providing a custom subscription plan resolver', function () {
    config(['spike.subscriptions' => null]);

    $firstPlan = new SubscriptionPlan(
        id: 'first',
        name: 'first plan',
        payment_provider_price_id: 'first_plan_stripe_id',
        provides_monthly: [
            CreditAmount::make(5000),
        ],
    );

    Spike::resolveSubscriptionPlansUsing(function () use ($firstPlan) {
        return [$firstPlan];
    });

    expect(Spike::subscriptionPlans())->toHaveCount(1)
        ->and(Spike::subscriptionPlans()->first())
            ->toBeInstanceOf(SubscriptionPlan::class)
            ->id->toBe($firstPlan->id)
            ->name->toBe($firstPlan->name)
            ->provides_monthly->toBe($firstPlan->provides_monthly)
            ->payment_provider_price_id->toBe($firstPlan->payment_provider_price_id)
            ->isMonthly()->toBeTrue();
});

test('Spike::resolveSubscriptionPlansUsing() works with different billables', function () {
    config(['spike.subscriptions' => null]);

    $firstPlan = new SubscriptionPlan(
        id: 'first',
        name: 'first plan',
        payment_provider_price_id: 'first_plan_stripe_id',
    );
    $secondPlan = new SubscriptionPlan(
        id: 'second',
        name: 'second plan',
        payment_provider_price_id: 'second_plan_stripe_id',
    );

    Spike::resolveSubscriptionPlansUsing(fn () => [$firstPlan]);
    Spike::billable($secondBillable = 'second_billable')
        ->resolveSubscriptionPlansUsing(fn () => [$secondPlan]);

    expect(Spike::subscriptionPlans())->toHaveCount(1)
        ->and(Spike::subscriptionPlans()->first())
        ->id->toBe($firstPlan->id);

    $secondBillableSubscriptionPlans = Spike::billable($secondBillable)->subscriptionPlans();
    expect($secondBillableSubscriptionPlans)->toHaveCount(1)
        ->and($secondBillableSubscriptionPlans->first())
        ->id->toBe($secondPlan->id);
});

test('Spike::resolveSubscriptionPlansUsing() allows providing array config instead of SubsriptionPlan objects', function () {
    config(['spike.subscriptions' => null]);

    $firstPlan = [
        'id' => 'first',
        'name' => 'first plan',
        'provides_monthly' => [
            CreditAmount::make(5000),
        ],
        'payment_provider_price_id_monthly' => 'first_payment_provider_price_id',
    ];

    Spike::resolveSubscriptionPlansUsing(fn () => [$firstPlan]);

    expect(Spike::subscriptionPlans())->toHaveCount(1)
        ->and(Spike::subscriptionPlans()->first())
        ->toBeInstanceOf(SubscriptionPlan::class)
        ->id->toBe($firstPlan['id'])
        ->name->toBe($firstPlan['name'])
        ->provides_monthly->toBe($firstPlan['provides_monthly'])
        ->payment_provider_price_id->toBe($firstPlan['payment_provider_price_id_monthly'])
        ->isMonthly()->toBeTrue();
});

test('subscription plan provides must implement the ProvidableContract interface', function () {
    config(['spike.subscriptions' => [[
        'id' => 'standard',
        'name' => 'Standard',
        'short_description' => 'test',
        'payment_provider_price_id_monthly' => 'standard_price_id_monthly',
        'price_in_cents_monthly' => 10_00,
        'provides_monthly' => [
            new stdClass(),
        ],
    ]]]);

    Spike::subscriptionPlans();
})->throws(
    InvalidArgumentException::class,
    'The class ' . get_class(new stdClass()) . ' must implement the ' . \Opcodes\Spike\Contracts\Providable::class . ' interface.'
);

test('backwards compatibility with the v2 config', function () {
    config(['spike.subscriptions' => [$standardConfig = [
        'id' => 'standard',
        'name' => 'Standard',
        'short_description' => 'test',
        'payment_provider_price_id_monthly' => 'standard_price_id_monthly',
        'payment_provider_price_id_yearly' => 'standard_price_id_yearly',
        'price_in_cents_monthly' => 10_00,
        'price_in_cents_yearly' => 90_00,
        'monthly_credits' => 500,
        'options' => [
            'foo' => 'bar',
        ],
    ]]]);

    // when there's no free plan configured, it creates a default free plan (monthly & yearly) automatically.
    expect(Spike::subscriptionPlans())->toHaveCount(2)
        ->and(Spike::monthlySubscriptionPlans())->toHaveCount(1)
        ->and(Spike::monthlySubscriptionPlans()->filter->isPaid()->first())
            ->toBeInstanceOf(SubscriptionPlan::class)
            ->id->toBe($standardConfig['id'])
            ->name->toBe($standardConfig['name'])
            ->short_description->toBe($standardConfig['short_description'])
            ->payment_provider_price_id->toBe($standardConfig['payment_provider_price_id_monthly'])
            ->price_in_cents->toBe($standardConfig['price_in_cents_monthly'])
            ->provides_monthly->toEqual([
                CreditAmount::make($standardConfig['monthly_credits'])
            ])
            ->options->toBe($standardConfig['options'])
        ->and(Spike::yearlySubscriptionPlans())->toHaveCount(1)
        ->and(Spike::yearlySubscriptionPlans()->filter->isPaid()->first())
            ->toBeInstanceOf(SubscriptionPlan::class)
            ->id->toBe($standardConfig['id'])
            ->name->toBe($standardConfig['name'])
            ->short_description->toBe($standardConfig['short_description'])
            ->payment_provider_price_id->toBe($standardConfig['payment_provider_price_id_yearly'])
            ->price_in_cents->toBe($standardConfig['price_in_cents_yearly'])
            ->provides_monthly->toEqual([
                CreditAmount::make($standardConfig['monthly_credits'])
            ])
            ->options->toBe($standardConfig['options']);
});

it('does not return monthly plan if only yearly price is configured', function () {
    config(['spike.subscriptions' => [[
        'id' => 'standard',
        'name' => 'Standard',
        'short_description' => 'test',
        'payment_provider_price_id_yearly' => 'standard_price_id_yearly',
        'price_in_cents_yearly' => 90_00,
    ]]]);

    expect(Spike::subscriptionPlans()->contains(fn ($plan) => $plan->id === 'standard' && $plan->isMonthly()))
        ->toBeFalse();
});
