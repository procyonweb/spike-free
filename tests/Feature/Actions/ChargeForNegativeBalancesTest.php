<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Actions\ChargeForNegativeBalances;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\SpikeServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    Credits::allowNegativeBalance();
    config(['spike.credit_types' => [
        [
            'id' => 'credits',
            'payment_provider_price_id' => 'price_single_credit',
        ],
        [
            'id' => 'sms',
            'payment_provider_price_id' => 'price_single_sms',
        ],
    ]]);
});

it('can charge for negative credit balances', function () {
    $billable = createBillable();
    Credits::billable($billable)->spend(10);
    Credits::billable($billable)->type('sms')->spend(5);
    PaymentGateway::partialMock()
        ->shouldReceive('invoiceAndPayItems')
        ->with([
            'price_single_credit' => 10,
            'price_single_sms' => 5,
        ])
        ->andReturnTrue()
        ->once();

    $action = new ChargeForNegativeBalances();
    $action->handle($billable);

    // a new credit purchase is made, and balance is reset to 0
    $this->assertDatabaseHas(CreditTransaction::class, [
        'billable_type' => $billable->getMorphClass(),
        'billable_id' => $billable->getKey(),
        'type' => CreditTransaction::TYPE_PRODUCT,
        'credit_type' => 'credits',
        'credits' => 10,
    ]);
    $this->assertDatabaseHas(CreditTransaction::class, [
        'billable_type' => $billable->getMorphClass(),
        'billable_id' => $billable->getKey(),
        'type' => CreditTransaction::TYPE_PRODUCT,
        'credit_type' => 'sms',
        'credits' => 5,
    ]);
    expect(Credits::billable($billable)->balance())->toBe(0);
    expect(Credits::billable($billable)->type('sms')->balance())->toBe(0);
});

it('throws exception if no payment provider price ID found for credit type', function () {
    $billable = createBillable();
    Credits::billable($billable)->type('sms')->spend(10);
    config(['spike.credit_types' => [
        [
            'id' => 'credits',
            'payment_provider_price_id' => 'price_single_credit',
        ],
        [
            'id' => 'sms',
            'payment_provider_price_id' => null,
        ],
    ]]);

    $action = new ChargeForNegativeBalances();
    expect(fn () => $action->handle($billable))->toThrow(
        RuntimeException::class,
        'No payment provider price ID found for credit type "sms". Cannot charge for the negative balance.'
    );
});

it('does nothing if there are no negative balances', function () {
    $billable = createBillable();
    Credits::billable($billable)->add(10);

    $action = new ChargeForNegativeBalances();
    $action->handle($billable);

    expect(Credits::billable($billable)->balance())->toBe(10);
});

it('does not charge for credit types that have charges disabled', function () {
    config(['spike.credit_types' => [
        [
            'id' => 'credits',
            'payment_provider_price_id' => 'price_single_credit',
        ],
        [
            'id' => 'sms',
            'payment_provider_price_id' => 'price_single_sms',
            'charge_negative_balances' => false,
        ],
    ]]);
    $billable = createBillable();
    Credits::billable($billable)->spend(10);
    Credits::billable($billable)->type('sms')->spend(5);
    PaymentGateway::partialMock()
        ->shouldReceive('invoiceAndPayItems')
        ->with([
            'price_single_credit' => 10,
        ])
        ->andReturnTrue()
        ->once();

    $action = new ChargeForNegativeBalances();
    $action->handle($billable);

    expect(Credits::billable($billable)->balance())->toBe(0);
    expect(Credits::billable($billable)->type('sms')->balance())->toBe(-5);
});
