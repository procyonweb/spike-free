<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Stripe\Subscription;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

it('calculates correct monthly renewal date', function ($todaysDate, $renewalDate, $expectedMonthlyRenewalDate) {
    testTime()->freeze($todaysDate);
    $user = createBillable();
    PaymentGateway::fake();
    PaymentGateway::partialMock()->shouldReceive('getRenewalDate')
        ->andReturn($renewalDate ? Carbon::parse($renewalDate) : $renewalDate);

    $actualMonthlyRenewalDate = optional($user->subscriptionMonthlyRenewalDate())->toDateTimeString();
    expect($actualMonthlyRenewalDate)->toBe($expectedMonthlyRenewalDate);
})->with([
    'no subscription' => ['2022-05-13 14:00:00', null, '2022-06-13 14:00:00'],
    'two days away' => ['2022-05-13 14:00:00', '2022-05-15 20:20:20', '2022-05-15 20:20:20'],
    'more than a month away' => ['2022-05-13 14:00:00', '2022-06-15 20:20:20', '2022-05-15 20:20:20'],
    'more than a year away' => ['2022-05-13 14:00:00', '2023-05-15 20:20:20', '2022-05-15 20:20:20'],
    'exactly a month away' => ['2022-05-13 14:00:00', '2022-06-13 14:00:00', '2022-05-13 14:00:00'],
    'exactly a month away with overflow' => ['2022-04-30 14:00:00', '2022-05-31 14:00:00', '2022-04-30 14:00:00'],
    'exactly two months away' => ['2022-05-31 14:00:00', '2022-07-31 14:00:00', '2022-05-31 14:00:00'],
    'overflows over several months' => ['2022-05-13 14:00:00', '2022-12-31 14:00:00', '2022-05-31 14:00:00'],
    'overflows around Feb 28th' => ['2022-01-13 14:00:00', '2022-12-31 14:00:00', '2022-01-31 14:00:00'],
    'yearly subscription' => ['2022-05-13 14:00:00', '2023-05-13 14:00:00', '2022-05-13 14:00:00'],
    'one day before renewal' => ['2022-05-12 14:00:00', '2022-05-13 14:00:00', '2022-05-13 14:00:00'],
    'one day before far-away renewal' => ['2022-05-12 14:00:00', '2022-08-13 14:00:00', '2022-05-13 14:00:00'],
]);

it('uses renews_at when there\'s no payment subscription stripe_id present', function () {
    $user = createBillable();
    $createdAt = now()->setTime(14, 0, 0);
    $expectedRenewalDate = $createdAt->copy()->addDay();
    Subscription::factory()->create([
        $user->getForeignKey() => $user->id,
        'stripe_id' => '',
        'created_at' => $createdAt,
        'renews_at' => $expectedRenewalDate,
    ]);

    $actualMonthlyRenewalDate = optional($user->subscriptionMonthlyRenewalDate())->toDateTimeString();
    expect($actualMonthlyRenewalDate)->toEqual($expectedRenewalDate->toDateTimeString());

    // even if we go forward in time, the renewal date should stay the same - just one month after created_at
    testTime()->addMonthsNoOverflow(2);
    $actualMonthlyRenewalDate = optional($user->subscriptionMonthlyRenewalDate())->toDateTimeString();
    expect($actualMonthlyRenewalDate)->toEqual($expectedRenewalDate->copy()->addMonthsNoOverflow(2)->toDateTimeString());
});

it('returns payment gateway monthly renewal date', function () {
    $billable = createBillable();
    $paymentProviderRenewalDate = now()->addWeek();
    PaymentGateway::fake()->setRenewalDate($paymentProviderRenewalDate);

    expect($billable->subscriptionMonthlyRenewalDate())
        ->toEqual($paymentProviderRenewalDate);
});

it('returns the next month renewal date if the payment provider renewal date is far into future', function () {
    testTime()->setDay(1);
    $billable = createBillable();
    $paymentProviderRenewalDate = now()->addMonths(5)->setDay(15);
    PaymentGateway::fake()->setRenewalDate($paymentProviderRenewalDate);

    expect($billable->subscriptionMonthlyRenewalDate())
        ->toEqual(now()->setDay(15));
});

it('returns today\'s date if the payment provider renewal day is the same as today\'s day', function () {
    testTime()->setDay(15);
    $billable = createBillable();
    $paymentProviderRenewalDate = now()->addMonths(5)->setDay(15);
    PaymentGateway::fake()->setRenewalDate($paymentProviderRenewalDate);

    expect($billable->subscriptionMonthlyRenewalDate())
        ->toEqual(now());
});

it('returns the correct date if user was never subscribed', function () {
    $billable = createBillable();

    expect($billable->subscriptionMonthlyRenewalDate())
        ->toEqual($billable->created_at->copy()->addMonthNoOverflow());

    testTime()->addMonthNoOverflow()->addDay();

    expect($billable->subscriptionMonthlyRenewalDate())
        ->toEqual($billable->created_at->copy()->addMonthsNoOverflow(2));
});

it('returns the correct date if user was previously subscribed', function () {
    testTime()->setDay(20);
    $billable = createBillable();
    Subscription::factory()->create([
        $billable->getForeignKey() => $billable->getKey(),
        'stripe_id' => 'fake-stripe-id',
        'ends_at' => $endsAt = now()->setDay(15)->setMicro(0),
    ]);

    expect($billable->subscriptionMonthlyRenewalDate())
        ->toEqual($endsAt->copy()->addMonthNoOverflow());

    testTime()->addMonth();

    expect($billable->subscriptionMonthlyRenewalDate())
        ->toEqual($endsAt->copy()->addMonthsNoOverflow(2));
});
