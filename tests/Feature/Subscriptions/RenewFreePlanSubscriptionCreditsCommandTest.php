<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SubscriptionPlan;
use Opcodes\Spike\Tests\Fixtures\SampleProvidable;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

beforeEach(function () {
    // For ease of testing, we'll assume 1 credit per day is given for Standard plan,
    // and 2 credits per day for Pro plan
    $credits = intval(now()->diffInDays(now()->addMonthNoOverflow(), true));

    $this->standardPlan = setupMonthlySubscriptionPlan($credits, 100);
    $this->standardPlanYearly = setupYearlySubscriptionPlan($credits, 1000);
    $this->proPlan = setupMonthlySubscriptionPlan($credits * 2, 200);
    $this->proPlanYearly = setupYearlySubscriptionPlan($credits * 2, 2000);
    $this->freePlan = SubscriptionPlan::defaultFreePlan();

    PaymentGateway::fake();
    $this->user = createBillable();
    Spike::resolve(function () {
        throw new \Exception('This should not have been called.');
    });
});

it('renews free plan credits', function () {
    config(['spike.subscriptions' => [
        [
            'id' => 'free',
            'name' => 'Free',
            'provides_monthly' => [CreditAmount::make($credits = 10)],
        ],
    ]]);

    $this->artisan('spike:renew-subscription-providables');

    expect(CreditTransaction::onlySubscriptions()->count())->toBe(1);
    $transaction = CreditTransaction::onlySubscriptions()->latest('id')->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->expires_at)->toBeNull()
        ->and($transaction->credits)->toBe($credits)
        ->and($this->user->credits()->balance())->toBe($credits);

    // calling the command will not renew credits until a month is passed
    testTime()->addDay();

    $this->artisan('spike:renew-subscription-providables');
    expect(CreditTransaction::onlySubscriptions()->count())->toBe(1);

    testTime()->addMonthNoOverflow();
    $this->artisan('spike:renew-subscription-providables');
    expect(CreditTransaction::onlySubscriptions()->count())->toBe(2)
        ->and($transaction->fresh()->expired())->toBeTrue();

    $newTransaction = CreditTransaction::onlySubscriptions()->latest('id')->first();
    expect($newTransaction)->not->toBeNull()
        ->and($newTransaction->expires_at)->toBeNull()
        ->and($newTransaction->credits)->toBe($credits)
        ->and($newTransaction->id)->not->toEqual($transaction->id)
        ->and($this->user->credits()->balance())->toBe($credits);
});

it('renews every providable on a free plan', function () {
    $customProvidable = new SampleProvidable;
    config(['spike.subscriptions' => [
        [
            'id' => 'free',
            'name' => 'Free',
            'provides_monthly' => [
                CreditAmount::make($credits = 10),
                $customProvidable,
            ],
        ],
    ]]);
    expect($customProvidable->providedMonthlySubscriptionCount)->toBe(0);

    $this->artisan('spike:renew-subscription-providables');
    expect($customProvidable->providedMonthlySubscriptionCount)->toBe(1);

    // running it again won't make a difference
    $this->artisan('spike:renew-subscription-providables');
    expect($customProvidable->providedMonthlySubscriptionCount)->toBe(1);

    // running it the day before renewal won't make a difference
    testTime()->addMonthNoOverflow()->subDay();
    $this->artisan('spike:renew-subscription-providables');
    expect($customProvidable->providedMonthlySubscriptionCount)->toBe(1);

    // running it on the day of renewal will renew the providable
    testTime()->addDay();
    $this->artisan('spike:renew-subscription-providables');
    expect($customProvidable->providedMonthlySubscriptionCount)->toBe(2);
});
