<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Paddle\Events\WebhookHandled;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Paddle\Listeners\PaddleEventListener;
use Opcodes\Spike\Paddle\Subscription;
use Opcodes\Spike\Paddle\SubscriptionItem;

uses(RefreshDatabase::class);

beforeAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = \Opcodes\Spike\PaymentProvider::Paddle;
});
afterAll(function () {
    \Opcodes\Spike\SpikeServiceProvider::$_cached_payment_provider = null;
});

beforeEach(function () {
    $this->standardCredits = 100;
    config(['spike.subscriptions' => [[
        'id' => 'standard',
        'name' => 'standard',
        'payment_provider_price_id_monthly' => 'pri_01',
        'provides_monthly' => [
            CreditAmount::make($this->standardCredits),
        ]
    ]]]);
    $this->standardPlan = Spike::findSubscriptionPlan('pri_01');
    PaymentGateway::fake();
});

it('handles subscription.created event and distributes providables', function () {
    $billable = createBillable();
    $subscription = Subscription::factory()
        ->has(SubscriptionItem::factory(['price_id' => 'pri_01', 'status' => 'active', 'quantity' => 1]), 'items')
        ->create([
            'billable_id' => $billable->getKey(),
            'billable_type' => $billable->getMorphClass(),
            'paddle_id' => 'sub_01',
            'status' => 'active',
        ]);

    $webhookEvent = createSubscriptionCreatedWebhookEvent(1, 'active');
    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    expect($billable->credits()->balance())->toBe($this->standardCredits);

    // repeating the listener will not apply the same credits again
    $listener->handle($webhookEvent);

    expect($billable->credits()->balance())->toBe($this->standardCredits);
});

it('does not call Spike::resolve()', function () {
    $billable = createBillable();
    $subscription = Subscription::factory()
        ->has(SubscriptionItem::factory(['price_id' => 'pri_01', 'status' => 'active', 'quantity' => 1]), 'items')
        ->create([
            'billable_id' => $billable->getKey(),
            'billable_type' => $billable->getMorphClass(),
            'paddle_id' => 'sub_01',
            'status' => 'active',
        ]);
    $webhookEvent = createSubscriptionCreatedWebhookEvent(1, 'active');
    $resolveCalled = false;
    Spike::resolve(function ($request) use (&$resolveCalled) {
        $resolveCalled = true;
    });

    $listener = new PaddleEventListener();
    $listener->handle($webhookEvent);

    $this->assertFalse($resolveCalled, 'Spike::resolve() should not be called');
});

function createSubscriptionCreatedWebhookEvent(int $quantity = 1, string $status = 'active'): WebhookHandled
{
    $plan = Spike::findSubscriptionPlan('pri_01');

    $json = <<<EOF
{
    "data": {
        "id": "sub_01",
        "items": [
            {
                "price": {
                    "id": "$plan->payment_provider_price_id"
                },
                "status": "active",
                "quantity": $quantity
            }
        ],
        "status": "$status",
        "transaction_id": "txn_01hndtdnf1v6wykpnz4dq3yfed"
    },
    "event_id": "evt_01hndte7g0pb61awqm41yr3yde",
    "event_type": "subscription.created",
    "occurred_at": "2024-01-30T18:34:55.360948Z",
    "notification_id": "ntf_01hndte7mf1dtd6pnhd96rkt0s"
}
EOF;

    return new WebhookHandled(json_decode($json, true));
}
