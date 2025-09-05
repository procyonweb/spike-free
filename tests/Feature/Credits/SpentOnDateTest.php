<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;
use function Spatie\PestPluginTestTime\testTime;

uses(RefreshDatabase::class);

beforeEach(function () {
    // We resolve the billable into a fake user.
    $fakeUser = createBillable();
    Spike::resolve(fn () => $fakeUser);
    
    // Set a fixed time for all tests
    testTime()->freeze('2023-01-15 12:00:00');
});

test('spentOnDate returns 0 when there are no usage transactions for the date', function () {
    // Add credits so we can spend
    CreditTransaction::factory()->create(['credits' => 100]);
    
    // Create a transaction for a different date
    testTime()->freeze('2023-01-10 12:00:00');
    Credits::spend(10);
    
    testTime()->freeze('2023-01-15 12:00:00');
    
    // Test with both string and Carbon parameter
    expect(Credits::spentOnDate('2023-01-15'))->toBe(0)
        ->and(Credits::spentOnDate(Carbon::parse('2023-01-15')))->toBe(0);
});

test('spentOnDate returns the correct amount when there is one usage transaction for the date', function () {
    // Add credits so we can spend
    CreditTransaction::factory()->create(['credits' => 100]);
    
    // Spend some credits on the test date
    Credits::spend(15);
    
    // Test with both string and Carbon parameter
    expect(Credits::spentOnDate('2023-01-15'))->toBe(15)
        ->and(Credits::spentOnDate(Carbon::parse('2023-01-15')))->toBe(15);
});

test('spentOnDate returns the correct total when there are multiple usage transactions for the date', function () {
    config(['spike.group_credit_spend_daily' => false]);
    
    // Add credits so we can spend
    CreditTransaction::factory()->create(['credits' => 100]);
    
    // Create multiple separate usage transactions on the same date
    Credits::spend(5);
    Credits::spend(10);
    Credits::spend(15);
    
    // Test with both string and Carbon parameter
    expect(Credits::spentOnDate('2023-01-15'))->toBe(30)
        ->and(Credits::spentOnDate(Carbon::parse('2023-01-15')))->toBe(30);
});

test('spentOnDate works with grouped usage transactions', function () {
    config(['spike.group_credit_spend_daily' => true]);
    
    // Add credits so we can spend
    CreditTransaction::factory()->create(['credits' => 100]);
    
    // Create grouped usage transactions
    Credits::spend(5);
    Credits::spend(10);
    Credits::spend(15);
    
    // Even though there's only one transaction, the total spent is the same
    expect(Credits::spentOnDate('2023-01-15'))->toBe(30);
    
    // Check that we really only have one usage transaction
    expect(CreditTransaction::onlyUsages()->count())->toBe(1);
});

test('spentOnDate respects the credit type', function () {
    // Add credits of different types
    CreditTransaction::factory()->create(['credits' => 100]);
    CreditTransaction::factory()->type('sms')->create(['credits' => 100]);
    
    // Spend different types of credits
    Credits::spend(15);
    Credits::type('sms')->spend(25);
    
    // Test that each type is counted separately
    expect(Credits::spentOnDate('2023-01-15'))->toBe(15)
        ->and(Credits::type('sms')->spentOnDate('2023-01-15'))->toBe(25);
});

test('spentOnDate only counts usage transactions and ignores other types', function () {
    // Add various transaction types
    CreditTransaction::factory()->create([
        'credits' => 100,
        'type' => CreditTransaction::TYPE_PRODUCT
    ]);
    CreditTransaction::factory()->create([
        'credits' => -30,
        'type' => CreditTransaction::TYPE_ADJUSTMENT
    ]);
    
    // Add some usage
    Credits::spend(15);
    
    // Should only count the usage (-15) and not the other transactions
    expect(Credits::spentOnDate('2023-01-15'))->toBe(15);
});

test('spentOnDate ignores transactions from different days', function () {
    // Add credits 
    CreditTransaction::factory()->create(['credits' => 100]);
    
    // Spend on the test date
    Credits::spend(15);
    
    // Move to a different date and spend more
    testTime()->addDay();
    Credits::spend(25);
    
    // Move to yet another date and spend more
    testTime()->addDay();
    Credits::spend(10);
    
    // Move back to the original test date
    testTime()->freeze('2023-01-15 12:00:00');
    
    // Should only count spending from the requested date
    expect(Credits::spentOnDate('2023-01-15'))->toBe(15)
        ->and(Credits::spentOnDate('2023-01-16'))->toBe(25)
        ->and(Credits::spentOnDate('2023-01-17'))->toBe(10);
});