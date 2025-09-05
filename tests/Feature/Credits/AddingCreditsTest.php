<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\CreditType;
use Opcodes\Spike\Events\CreditBalanceUpdated;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

beforeEach(function () {
    // We resolve the billable into a fake user. It's OK, because in these tests don't call any methods on the actual user.
    $fakeUser = createBillable();
    Spike::resolve(fn () => $fakeUser);
});

test('Credits::add() adds credits to the balance', function () {
    expect(Credits::balance())->toBe(0);

    Credits::add(10);

    $transaction = CreditTransaction::first();
    expect(Credits::balance())->toBe(10)
        ->and(CreditTransaction::count())->toBe(1)
        ->and($transaction->credits)->toBe(10)
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_ADJUSTMENT);
});

test('Credits::add() fires a CreditBalanceUpdated event', function () {
    Event::fake([CreditBalanceUpdated::class]);

    Credits::add(10);

    Event::assertDispatched(CreditBalanceUpdated::class, function (CreditBalanceUpdated $event) {
        return $event->balance === 10
            && $event->creditType->is(CreditType::default())
            && $event->relatedCreditTransaction instanceof CreditTransaction
            && $event->relatedCreditTransaction->isAdjustment()
            && $event->relatedCreditTransaction->credits === 10
            && $event->billable->is(Spike::resolve());
    });
});

test('Credits::add() accepts an optional note about the added credits', function () {
    Credits::add(10, $note = 'Test note');

    $transaction = CreditTransaction::latest('id')->first();
    expect(Credits::balance())->toBe(10)
        ->and($transaction->credits)->toBe(10)
        ->and($transaction->notes)->toBe($note);
});

test('Credits::add() accepts an optional expiry date to expire the added credits', function () {
    Credits::add(10, [
        'expires_at' => $expiryDate = now()->addDays(10),
    ]);

    $transaction = CreditTransaction::latest('id')->first();

    expect(Credits::balance())->toBe(10)
        ->and($transaction->credits)->toBe(10)
        ->and($transaction->expires_at?->toDateTimeString())->toBe($expiryDate->toDateTimeString());

    testTime()->addDays(11);

    expect(Credits::balance())->toBe(0)
        ->and($transaction->fresh()->expired())->toBeTrue();
});

test('Credits::add() takes into account the credit type', function () {
    config(['spike.credit_types' => [
        ['id' => 'credits'],
        ['id' => 'sms', 'translation_key' => 'sms']
    ]]);

    Credits::add(10);

    expect(Credits::balance())->toBe(10)
        ->and(Credits::type('sms')->balance())->toBe(0);

    Credits::type('sms')->add(5);

    expect(Credits::balance())->toBe(10)
        ->and(Credits::type('sms')->balance())->toBe(5);
});

test('Credits::add() allows backdating via created_at attribute', function () {
    $pastDate = now()->subDays(5);
    
    // Add credits with a backdated created_at
    $transaction = Credits::add(10, [
        'created_at' => $pastDate,
    ]);
    
    // Verify the transaction was created with the backdated timestamp
    expect($transaction->created_at->toDateString())->toBe($pastDate->toDateString())
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_ADJUSTMENT)
        ->and($transaction->credits)->toBe(10);
    
    // Check that the transaction exists in the database with correct date
    $dbTransaction = CreditTransaction::where('id', $transaction->id)->first();
    expect($dbTransaction->created_at->toDateString())->toBe($pastDate->toDateString());
});

test('Credits::add() allows backdating with notes and additional attributes', function () {
    $pastDate = now()->subDays(5);
    $expiryDate = now()->addDays(10);
    
    // Add credits with notes and backdated created_at
    $transaction = Credits::add(10, 'Test note', [
        'created_at' => $pastDate,
        'expires_at' => $expiryDate,
    ]);
    
    // Verify the transaction was created with all the custom attributes
    expect($transaction->created_at->toDateString())->toBe($pastDate->toDateString())
        ->and($transaction->notes)->toBe('Test note')
        ->and($transaction->expires_at->toDateString())->toBe($expiryDate->toDateString())
        ->and($transaction->type)->toBe(CreditTransaction::TYPE_ADJUSTMENT)
        ->and($transaction->credits)->toBe(10);
    
    // Check that the transaction exists in the database with correct attributes
    $dbTransaction = CreditTransaction::where('id', $transaction->id)->first();
    expect($dbTransaction->created_at->toDateString())->toBe($pastDate->toDateString())
        ->and($dbTransaction->notes)->toBe('Test note')
        ->and($dbTransaction->expires_at->toDateString())->toBe($expiryDate->toDateString());
});
