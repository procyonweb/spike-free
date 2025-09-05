<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SubscriptionPlan;

uses(RefreshDatabase::class);

test('Spike::resolveSubscriptionPlansUsing() passes billable and current plans', function () {
    $billable = createBillable();
    Spike::resolve(fn () => $billable);
    $plans = Spike::subscriptionPlans();
    $resolverCalled = false;

    Spike::resolveSubscriptionPlansUsing(function ($resolverBillable, $resolverPlans) use ($billable, $plans, &$resolverCalled) {
        expect($resolverBillable)->toBe($billable)
            ->and($resolverPlans->toArray())->toBe($plans->toArray());

        $resolverCalled = true;

        return [];
    });
    $plans = Spike::subscriptionPlans();

    $this->assertEmpty($plans);
    $this->assertTrue($resolverCalled, 'The subscription plan resolver was not called.');
});

test('Spike::resolveSubscriptionPlansUsing() marks the current plan', function () {
    config(['spike.subscriptions' => null]);
    $firstPlan = [
        'id' => 'first',
        'name' => 'first plan',
        'payment_provider_price_id_monthly' => 'first_payment_provider_price_id',
        'price_in_cents_monthly' => 1000,
    ];
    $secondPlan = [
        'id' => 'second',
        'name' => 'second plan',
        'payment_provider_price_id_monthly' => 'second_payment_provider_price_id',
        'price_in_cents_monthly' => 2000,
    ];

    Spike::resolveSubscriptionPlansUsing(fn () => [$firstPlan, $secondPlan]);

    // for now, there's no billable, so none of the plans are current.
    $plans = Spike::subscriptionPlans();
    expect($plans)->toHaveCount(2)
        ->and($plans->filter->isCurrent())->toBeEmpty('No plan should be marked as current');

    // now, let's create billable and subscribe them to one of the plans.
    $billable = createBillable();
    $billable->subscribeTo($plans->first(), false);
    expect($billable->isSubscribedTo($plans->first()))->toBeTrue();

    $plans = Spike::subscriptionPlans($billable);
    expect($plans)->toHaveCount(2)
        ->and($plans->filter->isCurrent())->toHaveCount(1)
        ->and($plans->filter->isCurrent()->first())
            ->id->toBe($firstPlan['id']);
});

test('Spike::resolveProductsUsing() passes billable and current products', function () {
    $billable = createBillable();
    Spike::resolve(fn () => $billable);
    $products = Spike::products();
    $resolverCalled = false;

    Spike::resolveProductsUsing(function ($resolverBillable, $resolverProducts) use ($billable, $products, &$resolverCalled) {
        expect($resolverBillable)->toBe($billable)
            ->and($resolverProducts->toArray())->toBe($products->toArray());

        $resolverCalled = true;

        return [];
    });
    $products = Spike::products();

    $this->assertEmpty($products);
    $this->assertTrue($resolverCalled, 'The product resolver was not called.');
});

test('Spike::findSubscriptionPlan() returns the default free plan for the "free" price ID', function () {
    config(['spike.subscriptions' => [
        [
            'id' => 'free',
            'name' => $configuredName = 'Configured free plan',
        ]
    ]]);

    $freePlan = Spike::findSubscriptionPlan('free');

    expect($freePlan)->not->toBeNull()
        ->and($freePlan->name)->toBe($configuredName);

    config(['spike.subscriptions' => null]);
    Spike::clearResolvedInstances();
    $defaultFreePlan = SubscriptionPlan::defaultFreePlan();

    $freePlan = Spike::findSubscriptionPlan('free');
    expect($freePlan)->not->toBeNull()
        ->and($freePlan->name)->toBe($defaultFreePlan->name);
});

test('Spike::mandateDataFromRequest() returns null if there is no request', function () {
    app()->forgetInstance('request');

    expect(Spike::mandateDataFromRequest())->toBeNull();
});

test('Spike::mandateDataFromRequest() returns null if there is no billable', function () {
    Spike::resolve(fn () => null);

    expect(Spike::mandateDataFromRequest())->toBeNull();
});

test('Spike::mandateDataFromRequest() returns the mandate data from the request', function () {
    // Resolve a billable
    Spike::resolve(fn () => createBillable());

    // Create a fake Request instance that contains certain IP and user-agent data.
    $request = new \Illuminate\Http\Request();
    $request->headers->set('User-Agent', $userAgent = 'Mozilla/5.0');
    $request->server->set('REMOTE_ADDR', $ipAddress = '123.123.123.123');
    app()->instance('request', $request);

    // Act
    $mandateData = Spike::mandateDataFromRequest();

    expect($mandateData)->toBe([
        'customer_acceptance' => [
            'type' => 'online',
            'online' => [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]
        ]
    ]);
});
