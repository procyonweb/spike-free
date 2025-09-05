<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Actions\Subscriptions\ProvideSubscriptionPlanMonthlyProvides;
use Opcodes\Spike\Actions\Subscriptions\RenewSubscriptionProvidables;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\ProvideHistory;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

beforeEach(function () {
    // We resolve the billable into a fake user. It's ok, because we don't call any methods on the actual user.
    $this->billable = createBillable();
    Spike::resolve(fn() => $this->billable);
});

test('ProvideSubscriptionPlanMonthlyProvides renews credits for a given subscription', function () {
    $plan = setupMonthlySubscriptionPlan(200);
    PaymentGateway::fake();
    $subscription = PaymentGateway::createSubscription($plan);
    app(ProvideSubscriptionPlanMonthlyProvides::class)
        ->handle($plan, $this->billable, $subscription->items->first());
    $transaction = Credits::currentSubscriptionTransaction();
    expect($transaction)->not->toBeNull();
    testTime()->addMonth();

    // a month later, credits are rotated
    app(ProvideSubscriptionPlanMonthlyProvides::class)
        ->handle($plan, $this->billable, $subscription->items->first());

    $transaction = $transaction->fresh();
    $newTransaction = Credits::currentSubscriptionTransaction();
    expect($transaction->expired())->toBeTrue()
        ->and($newTransaction)->not->toBeNull()
        ->and($newTransaction->credits)->toBe(200);
});

test('ProvideSubscriptionPlanMonthlyProvides renews multiple types of credits', function () {
    config(['spike.credit_types' => [
        ['id' => 'credits'],
        ['id' => 'sms'],
        ['id' => 'email'],
    ]]);
    $plan = setupMonthlySubscriptionPlan([
        CreditAmount::make(100),
        CreditAmount::make(50, 'email'),
        CreditAmount::make(10, 'sms')
    ]);
    PaymentGateway::fake();
    $subscription = PaymentGateway::createSubscription($plan);
    app(ProvideSubscriptionPlanMonthlyProvides::class)->handle(
        $plan, Spike::resolve(), $subscription->items->first()
    );

    // Let's first make sure the credits have been provided
    $defaultTransaction = Credits::currentSubscriptionTransaction();
    expect($defaultTransaction)->not->toBeNull()
        ->and($defaultTransaction->credits)->toBe(100);
    $emailTransaction = Credits::type('email')->currentSubscriptionTransaction();
    expect($emailTransaction)->not->toBeNull()
        ->and($emailTransaction->credits)->toBe(50);
    $smsTransaction = Credits::type('sms')->currentSubscriptionTransaction();
    expect($smsTransaction)->not->toBeNull()
        ->and($smsTransaction->credits)->toBe(10);

    testTime()->addMonth();

    // Action: renew the credits
    app(RenewSubscriptionProvidables::class)
        ->handle($this->billable, $subscription);

    // Check that the default credits have been renewed, old expired.
    $defaultTransaction = $defaultTransaction->fresh();
    expect($defaultTransaction->expired())->toBeTrue();
    $newDefaultTransaction = Credits::currentSubscriptionTransaction();
    expect($newDefaultTransaction)->not->toBeNull()
        ->and($newDefaultTransaction->credits)->toBe(100);

    // Check that the email credits have been renewed, old expired.
    $emailTransaction = $emailTransaction->fresh();
    expect($emailTransaction->expired())->toBeTrue();
    $newEmailTransaction = Credits::type('email')->currentSubscriptionTransaction();
    expect($newEmailTransaction)->not->toBeNull()
        ->and($newEmailTransaction->credits)->toBe(50);

    // Check that the sms credits have been renewed, old expired.
    $smsTransaction = $smsTransaction->fresh();
    expect($smsTransaction->expired())->toBeTrue();
    $newSmsTransaction = Credits::type('sms')->currentSubscriptionTransaction();
    expect($newSmsTransaction)->not->toBeNull()
        ->and($newSmsTransaction->credits)->toBe(10);

    // make sure that running the command again won't renew the credits
    $currentNumberOfTransactions = CreditTransaction::count();
    app(RenewSubscriptionProvidables::class)
        ->handle($this->billable, $subscription);

    expect(CreditTransaction::count())->toBe($currentNumberOfTransactions)
        ->and($newDefaultTransaction->fresh()->expired())->toBeFalse()
        ->and($newEmailTransaction->fresh()->expired())->toBeFalse()
        ->and($newSmsTransaction->fresh()->expired())->toBeFalse();
});

test('ProvideSubscriptionPlanMonthlyProvides handles null credit transaction when removing recent free plan provides', function () {
    // Setup: Create a free plan and a paid plan
    config(['spike.subscriptions' => [
        [
            'id' => 'free',
            'name' => 'Free Plan',
            'provides_monthly' => [CreditAmount::make(100)],
            'payment_provider_price_id_monthly' => null,
            'price_in_cents_monthly' => 0,
        ],
        [
            'id' => 'paid',
            'name' => 'Paid Plan',
            'provides_monthly' => [CreditAmount::make(500)],
            'payment_provider_price_id_monthly' => 'price_paid',
            'price_in_cents_monthly' => 1000,
        ]
    ]]);
    
    $billable = createBillable();
    Spike::resolve(fn() => $billable);
    
    // First, provide the free plan credits
    $freePlan = Spike::subscriptionPlans($billable)->first(fn($plan) => $plan->isFree());
    app(ProvideSubscriptionPlanMonthlyProvides::class)
        ->handle($freePlan, $billable);
    
    // Verify free plan credits were provided
    expect(Credits::balance())->toBe(100);
    
    // Now simulate a scenario where the credit transaction doesn't exist
    // This can happen when the free plan provides have been manually deleted or expired
    // but the provide history still exists
    
    // Delete all credit transactions but keep the provide history
    CreditTransaction::truncate();
    
    // Verify the credit transaction is gone but provide history exists
    expect(Credits::currentSubscriptionTransaction())->toBeNull();
    expect(ProvideHistory::hasProvidedMonthly($freePlan, CreditAmount::make(100), $billable))->toBeTrue();
    
    // Now try to provide paid plan credits - this should trigger the bug
    PaymentGateway::fake();
    $paidPlan = Spike::findSubscriptionPlan('price_paid');
    $subscription = PaymentGateway::createSubscription($paidPlan);
    
    // This should NOT throw an error about "credit_type" on null anymore
    $result = null;
    expect(fn() => $result = app(ProvideSubscriptionPlanMonthlyProvides::class)
        ->handle($paidPlan, $billable, $subscription->items->first()))
        ->not->toThrow(\Throwable::class);
    
    // Verify paid plan credits were provided
    expect(Credits::balance())->toBe(500);
});
