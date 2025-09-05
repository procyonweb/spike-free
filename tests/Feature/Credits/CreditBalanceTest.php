<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Opcodes\Spike\CreditBalance;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\CreditType;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

beforeEach(function () {
    // We resolve the billable into a fake user. It's OK, because in these tests don't call any methods on the actual user.
    $fakeUser = createBillable();
    Spike::resolve(fn () => $fakeUser);

    // Let's also set up an SMS credit type to test with.
    config(['spike.credit_types' => [
        ['id' => 'credits'],
        ['id' => 'sms', 'translation_key' => 'sms'],
    ]]);
});

it('resolves the billable automatically if a billable is not provided', function () {
    $user = createBillable();
    $secondUser = createBillable();
    Spike::resolve(fn () => $user);

    expect(Credits::getBillable())->toBe($user)
        ->and(Credits::getBillable())->not->toBe($secondUser);

    Spike::resolve(fn () => $secondUser);

    expect(Credits::getBillable())->toBe($secondUser)
        ->and(Credits::getBillable())->not->toBe($user);
});

test('Credits::balance() starts off with an empty balance', function () {
    expect(Credits::balance())->toBe(0)
        ->and(Credits::type('sms')->balance())->toBe(0);
});

test('Credits::balance() updates after a credit transaction is created', function () {
    CreditTransaction::factory()->create(['credits' => 20]);
    expect(Credits::balance())->toBe(20);

    CreditTransaction::factory()->create(['credits' => -5]);
    expect(Credits::balance())->toBe(15);

    CreditTransaction::factory()->type('sms')->create(['credits' => 50]);
    expect(Credits::type('sms')->balance())->toBe(50);

    CreditTransaction::factory()->type('sms')->create(['credits' => -20]);
    expect(Credits::type('sms')->balance())->toBe(30);
});

test('Credits::balance() is scoped to a billable', function () {
    CreditTransaction::factory()->create(['credits' => 20]);
    expect(Credits::balance())->toBe(20);

    $anotherBillable = createBillable();
    expect(Credits::billable($anotherBillable)->balance())->toBe(0);
    CreditTransaction::factory()->forBillable($anotherBillable)->create(['credits' => 50]);
    CreditTransaction::factory()->type('sms')->forBillable($anotherBillable)->create(['credits' => 20]);

    expect(Credits::billable($anotherBillable)->balance())->toBe(50)
        ->and(Credits::billable($anotherBillable)->type('sms')->balance())->toBe(20)
        // The original/default billable should be unchanged.
        ->and(Credits::balance())->toBe(20)
        ->and(Credits::type('sms')->balance())->toBe(0);
});

test('Credits::balance() correctly calculates expired credits', function () {
    // when the credits expire, we don't want them to be removed from the account as if they had never existed.
    // We should first consume credits that have the earliest expiration.
    // E.g. we have 100 credits that expire in a week, and 200 more credits that expire in 2 weeks.
    // One week has passed, and we only spent 50 credits. How many credits we have in the balance? => 200.
    // If we had used 150 credits instead, we would have had 150 credits left after one week.

    CreditTransaction::factory()->create(['credits' => 100, 'expires_at' => now()->addWeek()]);
    CreditTransaction::factory()->create(['credits' => 200, 'expires_at' => now()->addWeeks(2)]);
    Credits::spend(50);
    expect(Credits::balance())->toBe(250);

    // now, once we move the time a week forward, the unused 50 credits from the first transaction should expire.
    // But because we still have one more week left for the other 200 credits, we should see those in the balance.
    testTime()->addWeek();
    expect(Credits::balance())->toBe(200);

    // when we move one more week forward, those 200 credits should expire too.
    testTime()->addWeek();
    expect(Credits::balance())->toBe(0);
});

test('Credits::balance() correctly calculates expired credits across multiple purchases when overflowing', function () {
    CreditTransaction::factory()->create(['credits' => 100, 'expires_at' => now()->addWeek()]);
    CreditTransaction::factory()->create(['credits' => 200, 'expires_at' => now()->addWeeks(2)]);

    Credits::spend(130);
    expect(Credits::balance())->toBe(170);

    testTime()->addWeek();
    expect(Credits::balance())->toBe(170);
});

test('Credits::balance() correctly calculates credits after usage without overflowing', function () {
    CreditTransaction::factory()->create(['credits' => 100, 'expires_at' => now()->addWeek()]);
    CreditTransaction::factory()->create(['credits' => 200, 'expires_at' => now()->addWeeks(2)]);

    Credits::spend(30);
    expect(Credits::balance())->toBe(270);

    testTime()->addWeek();
    // the remaining 70 credits from the first purchase should expire, leaving only the 200 credits from the second purchase.
    expect(Credits::balance())->toBe(200);
});

test('Credits::balance() correctly calculates multiple credits the day after they were purchased', function () {
    config(['spike.credit_types' => [
        ['id' => 'credits'],
        ['id' => 'sms'],
    ]]);
    CreditTransaction::factory()->create(['credits' => 100, 'credit_type' => 'credits']);
    CreditTransaction::factory()->create(['credits' => 50, 'credit_type' => 'sms']);

    expect(Credits::balance())->toBe(100)
        ->and(Credits::type('sms')->balance())->toBe(50);

    testTime()->addDay();
    Cache::driver('array')->clear();

    expect(Credits::balance())->toBe(100)
        ->and(Credits::type('sms')->balance())->toBe(50);
});

test('Credits::balance() calculates at a considerably quick speed', function () {
    CreditTransaction::factory()
        ->count(20)
        ->sequence(fn ($sequence) => ['expires_at' => now()->addDays($sequence->index)])
        ->create(['credits' => 1000]);

    Collection::times(100, function ($number) {
        Credits::spend(50);
        testTime()->addHours(2);
    });

    Credits::clearCache();
    $start = microtime(true);
    Credits::balance();
    $end = microtime(true);
    expect($end - $start)->toBeLessThan(0.05);
});

test('Credits::balance() calculates the balance correctly after multiple credit usages, purchases, etc', function () {
    // here's a usage case - user subscribes to 300 credits per month (10 credits per day), but the next day
    // upgrades to 600 credits per month. By that time, they have already used up around 150 credits,
    // which is more than the prorated quota for a single day. How should this be handled?
    $subscriptionTransaction = CreditTransaction::factory()->subscription()->create(['credits' => 300]);
    $usageTransaction = CreditTransaction::factory()->usage()->create(['credits' => -150]);

    testTime()->addDay();
    // the next day, we prorate previous subscription credits and expire it
    // then we also expire the usage
    $subscriptionTransaction->update(['credits' => 10, 'expires_at' => now()]);
    Credits::currentUsageTransaction()->expire();
    Credits::clearCache();

    CreditTransaction::factory()->subscription()->create(['credits' => 600]);

    expect(Credits::balance())->toBe(-150 + 10 + 600);
});

test('Credits::allBalances() gives a collection of the different types of balances', function () {
    config(['spike.credit_types' => [
        ['id' => 'credits'],
        ['id' => 'sms'],
        ['id' => 'email'],
    ]]);

    Credits::type('sms')->add(100);
    Credits::type('sms')->spend(20);

    Credits::type('email')->add(150);
    Credits::type('email')->spend(10);

    Credits::add(100);
    Credits::spend(50);

    expect($balances = Credits::allBalances())->toBeInstanceOf(Collection::class)
        ->toHaveCount(3)
        ->and($balances[0])->toBeInstanceOf(CreditBalance::class)
        ->and($balances[0]->type())->toEqual(CreditType::default())
        ->and($balances[0]->balance())->toBe(50)

        ->and($balances[1])->toBeInstanceOf(CreditBalance::class)
        ->and($balances[1]->type())->toEqual(CreditType::make('sms'))
        ->and($balances[1]->balance())->toBe(80)

        ->and($balances[2])->toBeInstanceOf(CreditBalance::class)
        ->and($balances[2]->type())->toEqual(CreditType::make('email'))
        ->and($balances[2]->balance())->toBe(140);
});
