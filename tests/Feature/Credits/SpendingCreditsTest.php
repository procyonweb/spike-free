<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\CreditType;
use Opcodes\Spike\Events\CreditBalanceUpdated;
use Opcodes\Spike\Exceptions\NotEnoughBalanceException;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

beforeEach(function () {
    // We resolve the billable into a fake user. It's OK, because in these tests don't call any methods on the actual user.
    $fakeUser = createBillable();
    Spike::resolve(fn () => $fakeUser);
});

test('Credits::spend() creates a credit usage transaction', function () {
    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::spend(5);

    $transaction = CreditTransaction::latest('id')->first();
    expect(Credits::balance())->toBe(15)
        ->and(CreditTransaction::count())->toBe(2)
        ->and($transaction->credits)->toBe(-5)
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_USAGE);
});

test('Credits::spend() reuses the same transaction for several spends on the same day', function () {
    config(['spike.group_credit_spend_daily' => true]);
    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::spend(5);
    Credits::spend(3);

    $transaction = CreditTransaction::latest('id')->first();
    expect(Credits::balance())->toBe(12)
        ->and(CreditTransaction::count())->toBe(2)
        ->and($transaction->credits)->toBe(-8)
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_USAGE);
});

test('Credits::spend() creates new usage transactions when spending across multiple days', function () {
    config(['spike.group_credit_spend_daily' => true]);
    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::spend(5);

    expect(CreditTransaction::count())->toBe(2);

    testTime()->addDay();

    Credits::spend(3);
    $transaction = CreditTransaction::latest('id')->first();
    expect(CreditTransaction::count())->toBe(3)
        ->and($transaction->credits)->toBe(-3)
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_USAGE)
        ->and(Credits::balance())->toBe(20 - 5 - 3);
});

test('Credits::spend() creates separate transaction if grouping is disabled', function () {
    config(['spike.group_credit_spend_daily' => false]);
    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::spend(1);
    Credits::spend(1);
    Credits::spend(1);

    expect(CreditTransaction::count())->toBe(1 + 3);
    $latestTransaction = CreditTransaction::latest('id')->first();
    expect($latestTransaction->credits)->toBe(-1)
        ->and($latestTransaction->type)->toBe(CreditTransaction::TYPE_USAGE)
        ->and(Credits::balance())->toBe(20 - 1 - 1 - 1);
});

test('Credits::spend() throws when trying to spend more than the available balance', function () {
    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::spend(30);
})->throws(NotEnoughBalanceException::class);

test('Credits::spend() fires a CreditBalanceUpdated event', function () {
    CreditTransaction::factory()->create(['credits' => 100]);
    Event::fake([CreditBalanceUpdated::class]);

    Credits::spend(10);

    Event::assertDispatched(CreditBalanceUpdated::class, function (CreditBalanceUpdated $event) {
        return $event->balance === 90   // new balance is 90
            && $event->creditType->is(CreditType::default())
            && $event->relatedCreditTransaction instanceof CreditTransaction
            && $event->relatedCreditTransaction->isUsage()
            && $event->relatedCreditTransaction->credits === -10
            && $event->billable->is(Spike::resolve());
    });
});

test('Credits::spend() takes into account the credit type', function () {
    CreditTransaction::factory()->create(['credits' => 100]);
    CreditTransaction::factory()->type('sms')->create(['credits' => 100]);

    Credits::spend(10);

    expect(Credits::balance())->toBe(90)
        ->and(Credits::type('sms')->balance())->toBe(100);

    Credits::type('sms')->spend(20);

    expect(Credits::balance())->toBe(90)
        ->and(Credits::type('sms')->balance())->toBe(80);
});

test('Credits::spend() allows spending if negative balances are allowed', function () {
    Credits::allowNegativeBalance();

    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::spend(30);

    expect(Credits::balance())->toBe(-10);
});

test('Credits::spend() allows adding a note to the transaction', function () {
    config(['spike.group_credit_spend_daily' => false]);
    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::spend(5, 'Test note');

    $transaction = CreditTransaction::latest('id')->first();
    expect($transaction->notes)->toBe('Test note')
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_USAGE)
        ->and($transaction->credits)->toBe(-5)
        ->and($transaction->credit_type->type)->toBe(CreditType::default()->type);
});

test('Credits::spend() does not allow adding note when grouping is enabled', function () {
    config(['spike.group_credit_spend_daily' => true]);
    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::spend(5, 'Test note');
})->throws('Cannot add notes when spending is grouped. Please see `spike.group_credit_spend_daily` config option.');

test('Credits::spend() accepts attributes as the second parameter', function () {
    config(['spike.group_credit_spend_daily' => false]);
    CreditTransaction::factory()->create(['credits' => 20]);

    $expiryDate = now()->addDays(10);
    
    Credits::spend(5, [
        'expires_at' => $expiryDate,
    ]);

    $transaction = CreditTransaction::latest('id')->first();
    expect($transaction->notes)->toBeNull()
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_USAGE)
        ->and($transaction->credits)->toBe(-5)
        ->and($transaction->expires_at?->toDateTimeString())->toBe($expiryDate->toDateTimeString());
});

test('Credits::spend() accepts attributes as the third parameter', function () {
    config(['spike.group_credit_spend_daily' => false]);
    CreditTransaction::factory()->create(['credits' => 20]);

    $expiryDate = now()->addDays(10);
    
    Credits::spend(5, 'Test note', [
        'expires_at' => $expiryDate,
    ]);

    $transaction = CreditTransaction::latest('id')->first();
    expect($transaction->notes)->toBe('Test note')
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_USAGE)
        ->and($transaction->credits)->toBe(-5)
        ->and($transaction->expires_at?->toDateTimeString())->toBe($expiryDate->toDateTimeString());
});

test('Credits::spend() does not allow adding attributes when grouping is enabled', function () {
    config(['spike.group_credit_spend_daily' => true]);
    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::spend(5, [
        'expires_at' => now()->addDays(10),
    ]);
})->throws('Cannot add attributes when spending is grouped. Please see `spike.group_credit_spend_daily` config option.');

test('spentOnDate correctly reports spending on a backdated transaction', function () {
    config(['spike.group_credit_spend_daily' => false]);
    
    // Setup the test with a billable and initial credit balance
    $billable = createBillable();
    Spike::resolve(fn () => $billable);
    
    // Add initial credits (20)
    Credits::add(20);
    
    $pastDate = now()->subDays(5);
    
    // Create a backdated transaction for spending credits
    $transaction = new CreditTransaction([
        'credit_type' => CreditType::default()->type,
        'type' => CreditTransaction::TYPE_USAGE,
        'credits' => -5,
    ]);
    $transaction->billable()->associate($billable);
    
    // Use timestamps property to disable automatic timestamp handling
    $transaction->timestamps = false;
    $transaction->created_at = $pastDate;
    $transaction->updated_at = $pastDate;
    $transaction->save();
    
    // We'll check that the credit balance is still 20-5=15, even though the transaction is backdated
    // But we're more concerned with testing that spentOnDate works correctly with backdated transactions
    
    // Check that the transaction was properly backdated
    expect($transaction->fresh()->created_at->toDateString())->toBe($pastDate->toDateString())
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_USAGE)
        ->and($transaction->credits)->toBe(-5);
        
    // Check that spentOnDate correctly reports usage on the backdated date
    expect(Credits::spentOnDate($pastDate->toDateString()))->toBe(5)
        ->and(Credits::spentOnDate(now()->toDateString()))->toBe(0); // Nothing spent today
});

test('Credits::spend() allows backdating usage via created_at attribute', function () {
    config(['spike.group_credit_spend_daily' => false]);
    
    // Setup the test with a billable and initial credit balance
    $billable = createBillable();
    Spike::resolve(fn () => $billable);
    
    // Add initial credits (20)
    Credits::add(20);
    
    $pastDate = now()->subDays(5);
    
    // Spend credits with backdated created_at
    Credits::spend(5, [
        'created_at' => $pastDate,
    ]);
    
    // Check that the transaction was properly backdated
    $transaction = CreditTransaction::where('type', CreditTransaction::TYPE_USAGE)->first();
    
    expect($transaction->created_at->toDateString())->toBe($pastDate->toDateString())
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_USAGE)
        ->and($transaction->credits)->toBe(-5);
        
    // Check that spentOnDate correctly reports the backdated spending
    expect(Credits::spentOnDate($pastDate->toDateString()))->toBe(5)
        ->and(Credits::spentOnDate(now()->toDateString()))->toBe(0); // Nothing spent today
});

test('Credits::spend() allows backdating with notes and additional attributes', function () {
    config(['spike.group_credit_spend_daily' => false]);
    
    // Setup the test with a billable and initial credit balance
    $billable = createBillable();
    Spike::resolve(fn () => $billable);
    
    // Add initial credits (20)
    Credits::add(20);
    
    $pastDate = now()->subDays(5);
    $expiryDate = now()->addDays(10);
    
    // Spend credits with notes and backdated created_at
    Credits::spend(5, 'Test note', [
        'created_at' => $pastDate,
        'expires_at' => $expiryDate,
    ]);
    
    // Check that the transaction was properly backdated with all attributes
    $transaction = CreditTransaction::where('type', CreditTransaction::TYPE_USAGE)->first();
    
    expect($transaction->created_at->toDateString())->toBe($pastDate->toDateString())
        ->and($transaction->notes)->toBe('Test note')
        ->and($transaction->expires_at->toDateString())->toBe($expiryDate->toDateString())
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_USAGE)
        ->and($transaction->credits)->toBe(-5);
        
    // Check that spentOnDate correctly reports the backdated spending
    expect(Credits::spentOnDate($pastDate->toDateString()))->toBe(5)
        ->and(Credits::spentOnDate(now()->toDateString()))->toBe(0); // Nothing spent today
});
