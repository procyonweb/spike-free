<?php

use Opcodes\Spike\CreditType;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;

beforeEach(function () {
    // just so we have two credit types to work with.
    config(['spike.credit_types' => [
        ['id' => 'credits'],
        ['id' => 'sms']
    ]]);
});

test('it does not allow negative balances by default', function () {
    expect(Credits::type('credits')->isNegativeBalanceAllowed())->toBeFalse();
    expect(Credits::type('sms')->isNegativeBalanceAllowed())->toBeFalse();
});

it('can allow negative balance on all credit types', function () {
    Credits::allowNegativeBalance();

    expect(Credits::type('credits')->isNegativeBalanceAllowed())->toBeTrue();
    expect(Credits::type('sms')->isNegativeBalanceAllowed())->toBeTrue();
});

it('can allow negative balance based on provided callback', function () {
    Credits::allowNegativeBalance(fn () => false);
    expect(Credits::type('credits')->isNegativeBalanceAllowed())->toBeFalse();

    Credits::allowNegativeBalance(fn () => true);
    expect(Credits::type('credits')->isNegativeBalanceAllowed())->toBeTrue();
});

it('receives billable and credit type within the callback', function () {
    $billable = createBillable();
    Spike::resolve(fn () => $billable);
    $paramsReceived = null;
    Credits::allowNegativeBalance(function ($creditType, $billable) use (&$paramsReceived) {
        $paramsReceived = func_get_args();
        return true;
    });

    Credits::type('credits')->isNegativeBalanceAllowed();
    expect($paramsReceived)->toHaveCount(2)
        ->and($paramsReceived[0])->toBeInstanceOf(CreditType::class)
        ->and($paramsReceived[0]->type)->toBe('credits')
        ->and($paramsReceived[1])->toBe($billable);

    $secondBillable = createBillable();
    Credits::billable($secondBillable)->type('sms')->isNegativeBalanceAllowed();
    expect($paramsReceived)->toHaveCount(2)
        ->and($paramsReceived[0])->toBeInstanceOf(CreditType::class)
        ->and($paramsReceived[0]->type)->toBe('sms')
        ->and($paramsReceived[1])->toBe($secondBillable);
});

it('receives billable and credit type within callback when using type-specific callbacks', function () {
    $billable = createBillable();
    Spike::resolve(fn () => $billable);
    $paramsReceived = null;
    Credits::type('sms')->allowNegativeBalance(function ($creditType, $billable) use (&$paramsReceived) {
        $paramsReceived = func_get_args();
        return true;
    });

    Credits::type('sms')->isNegativeBalanceAllowed();
    expect($paramsReceived)->toHaveCount(2)
        ->and($paramsReceived[0])->toBeInstanceOf(CreditType::class)
        ->and($paramsReceived[0]->type)->toBe('sms')
        ->and($paramsReceived[1])->toBe($billable);
});

it('can allow negative balance for a specific credit type', function () {
    Credits::type('sms')->allowNegativeBalance();

    expect(Credits::type('credits')->isNegativeBalanceAllowed())->toBeFalse();
    expect(Credits::type('sms')->isNegativeBalanceAllowed())->toBeTrue();
});

it('can disallow negative balance for a specific credit type', function () {
    Credits::allowNegativeBalance();
    Credits::type('sms')->allowNegativeBalance(false);

    expect(Credits::type('credits')->isNegativeBalanceAllowed())->toBeTrue();
    expect(Credits::type('sms')->isNegativeBalanceAllowed())->toBeFalse();
});
