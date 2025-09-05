<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Paddle\Events\WebhookHandled;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Paddle\Listeners\PaddleEventListener;

uses(RefreshDatabase::class);

beforeAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = \Opcodes\Spike\PaymentProvider::Paddle;
});
afterAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = null;
});

beforeEach(function () {
    $this->standardCredits = 100;
    $this->businessCredits = 200;
    config(['spike.products' => [[
        'id' => 'standard',
        'name' => 'standard',
        'payment_provider_price_id' => 'pri_01',
        'provides' => [
            CreditAmount::make($this->standardCredits),
        ]
    ], [
        'id' => 'business',
        'name' => 'business',
        'payment_provider_price_id' => 'pri_02',
        'provides' => [
            CreditAmount::make($this->businessCredits),
        ]
    ]]]);
    $this->standardProduct = Spike::findProduct('standard');
    $this->businessProduct = Spike::findProduct('business');
});

it('handles transaction.paid event', function () {
    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory([
            'product_id' => $this->standardProduct->id,
            'quantity' => 1
        ]), 'items')
        ->create();

    $webhookEvent = createTransactionPaidWebhookEvent($cart, ['standard' => 1]);
    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    $cart->refresh();

    expect($cart->paid())->toBeTrue()
        ->and($billable->credits()->balance())->toBe($this->standardCredits);
});

it('does not call Spike::resolve()', function () {
    $billable = createBillable();
    $cart = Cart::factory()
        ->forBillable($billable)
        ->has(CartItem::factory([
            'product_id' => $this->standardProduct->id,
            'quantity' => 1
        ]), 'items')
        ->create();
    $webhookEvent = createTransactionPaidWebhookEvent($cart, ['standard' => 1]);
    $resolveCalled = false;
    Spike::resolve(function ($request) use (&$resolveCalled) {
        $resolveCalled = true;
    });

    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    $this->assertFalse($resolveCalled, 'Spike::resolve() should not be called');
});

function createTransactionPaidWebhookEvent(Cart $cart, array $productQuantities = []): WebhookHandled
{
    $currency = config('cashier.currency', 'USD');
    $items = collect($productQuantities)
        ->map(fn($quantity, $productId) => [
            'price_id' => Spike::findProduct($productId)->payment_provider_price_id,
            'quantity' => $quantity,
        ])
        ->filter(fn($item) => $item['quantity'] > 0)
        ->values()
        ->map(fn($productQuantity) => [
            'price' => [
                'id' => $productQuantity['price_id'],
                'unit_price' => [
                    'amount' => $productQuantity['unit_price'] ?? 0,
                    'currency_code' => $currency,
                ],
            ],
            'price_id' => $productQuantity['price_id'],
            'quantity' => $productQuantity['quantity'],
        ]);
    $total = $items->sum(function ($item) {
        return intval($item['price']['unit_price']['amount'] * $item['quantity']);
    });

    return createPaddleTransactionWebhookEvent('transaction.paid', [
        'id' => 'txn_01hn2b49xz6g0zjqv5ysv229fd',
        'billed_at' => now()->toRfc3339String(),
        'customer_id' => $cart->billable->customer->paddle_id,
        'items' => $items->toArray(),
        'currency_code' => $currency,
        'status' => 'paid',
        'details' => [
            'totals' => [
                'total' => (string) $total,
                'grand_total' => (string) $total,
            ],
        ],
        'custom_data' => [
            'spike_cart_id' => $cart->id,
        ],
    ]);
}
