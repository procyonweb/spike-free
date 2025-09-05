<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\CreditType;
use Opcodes\Spike\Events\CreditBalanceUpdated;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;

uses(RefreshDatabase::class);

beforeEach(function () {
    // We resolve the billable into a fake user. It's OK, because in these tests don't call any methods on the actual user.
    $fakeUser = createBillable();
    Spike::resolve(fn () => $fakeUser);
});

test('Credits::remove() removes credits from the balance', function () {
    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::remove(5);

    $transaction = CreditTransaction::latest('id')->first();
    expect(Credits::balance())->toBe(15)
        ->and(CreditTransaction::count())->toBe(2)
        ->and($transaction->credits)->toBe(-5)
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_ADJUSTMENT);
});

test('Credits::remove() fires a CreditBalanceUpdated event', function () {
    CreditTransaction::factory()->create(['credits' => 20]);
    Event::fake([CreditBalanceUpdated::class]);

    Credits::remove(5);

    Event::assertDispatched(CreditBalanceUpdated::class, function (CreditBalanceUpdated $event) {
        return $event->balance === 15   // new balance is 15
            && $event->creditType->is(CreditType::default())
            && $event->relatedCreditTransaction instanceof CreditTransaction
            && $event->relatedCreditTransaction->isAdjustment()
            && $event->relatedCreditTransaction->credits === -5
            && $event->billable->is(Spike::resolve());
    });
});

test('Credits::remove() accepts an optional note about the removed credits', function () {
    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::remove(10, $note = 'Test note');

    $transaction = CreditTransaction::latest('id')->first();
    expect(Credits::balance())->toBe(10)
        ->and($transaction->credits)->toBe(-10)
        ->and($transaction->notes)->toBe($note);
});

test('Credits::remove() throws when trying to remove more credits than available', function () {
    CreditTransaction::factory()->create(['credits' => 20]);

    Credits::remove(30);
})->throws(\Opcodes\Spike\Exceptions\NotEnoughBalanceException::class);

test('Credits::remove() takes into account the credit type', function () {
    CreditTransaction::factory()->create(['credits' => 20]);
    CreditTransaction::factory()->type('sms')->create(['credits' => 10]);

    Credits::remove(5);

    expect(Credits::balance())->toBe(15)
        ->and(Credits::type('sms')->balance())->toBe(10);

    Credits::type('sms')->remove(5);

    expect(Credits::balance())->toBe(15)
        ->and(Credits::type('sms')->balance())->toBe(5);
});

test('Credits::remove() allows negative credits when enabled', function () {
    Credits::allowNegativeBalance();

    Credits::remove(5);

    expect(Credits::balance())->toBe(-5);
});

test('Credits::remove() allows backdating via created_at attribute', function () {
    // First add some credits so we can remove them
    Credits::add(20);
    expect(Credits::balance())->toBe(20);
    
    $pastDate = now()->subDays(5);
    
    // Remove credits with a backdated created_at
    $transaction = Credits::remove(10, [
        'created_at' => $pastDate,
    ]);
    
    // Verify the transaction was created with the backdated timestamp
    expect($transaction->created_at->toDateString())->toBe($pastDate->toDateString())
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_ADJUSTMENT)
        ->and($transaction->credits)->toBe(-10);
    
    // Check that the transaction exists in the database with correct date
    $dbTransaction = CreditTransaction::where('id', $transaction->id)->first();
    expect($dbTransaction->created_at->toDateString())->toBe($pastDate->toDateString());
});

test('Credits::remove() allows backdating with notes and additional attributes', function () {
    // First add some credits so we can remove them
    Credits::add(20);
    expect(Credits::balance())->toBe(20);
    
    $pastDate = now()->subDays(5);
    $expiryDate = now()->addDays(10);
    
    // Remove credits with notes and backdated created_at
    $transaction = Credits::remove(10, 'Test note', [
        'created_at' => $pastDate,
        'expires_at' => $expiryDate,
    ]);
    
    // Verify the transaction was created with all the custom attributes
    expect($transaction->created_at->toDateString())->toBe($pastDate->toDateString())
        ->and($transaction->notes)->toBe('Test note')
        ->and($transaction->expires_at->toDateString())->toBe($expiryDate->toDateString())
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_ADJUSTMENT)
        ->and($transaction->credits)->toBe(-10);
    
    // Check that the transaction exists in the database with correct attributes
    $dbTransaction = CreditTransaction::where('id', $transaction->id)->first();
    expect($dbTransaction->created_at->toDateString())->toBe($pastDate->toDateString())
        ->and($dbTransaction->notes)->toBe('Test note')
        ->and($dbTransaction->expires_at->toDateString())->toBe($expiryDate->toDateString());
});
