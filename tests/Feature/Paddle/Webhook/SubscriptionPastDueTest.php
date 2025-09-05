<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Paddle\Events\WebhookHandled;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Paddle\Listeners\PaddleEventListener;
use Opcodes\Spike\Paddle\Subscription;
use Opcodes\Spike\Paddle\SubscriptionItem;
use Spatie\TestTime\TestTime;

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

it('does not provide credits when subscription state is past_due', function () {
    // Set up an active Paddle subscription
    $billable = createBillable();
    $subscription = Subscription::factory()
        ->has(SubscriptionItem::factory(['price_id' => 'pri_01', 'status' => 'active', 'quantity' => 1]), 'items')
        ->create([
            'billable_id' => $billable->getKey(),
            'billable_type' => $billable->getMorphClass(),
            'paddle_id' => 'sub_01',
            'status' => 'active',
        ]);

    // Simulate initial subscription webhook to provide credits
    $initialWebhookEvent = createSubscriptionUpdatedWebhookEvent('active');
    $listener = new PaddleEventListener();
    $listener->handle($initialWebhookEvent);

    // Verify initial credit balance
    expect($billable->credits()->balance())->toBe($this->standardCredits);
    $initialTransactionCount = CreditTransaction::count();

    // Move time forward one month
    TestTime::addMonth();

    $subscription->update(['status' => 'past_due']);
    // Simulate a subscription.updated webhook with past_due status
    $pastDueWebhookEvent = createSubscriptionUpdatedWebhookEvent('past_due');
    $listener->handle($pastDueWebhookEvent);

    // Assert no new credit transactions were created
    expect(CreditTransaction::count())->toBe($initialTransactionCount);

    // Assert no additional credits were provided
    expect($billable->credits()->balance())->toBe($this->standardCredits);
});

function createSubscriptionUpdatedWebhookEvent(string $status = 'active'): WebhookHandled
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
                "quantity": 1
            }
        ],
        "status": "$status",
        "transaction_id": "txn_01hndtdnf1v6wykpnz4dq3yfed"
    },
    "event_id": "evt_01hndte7g0pb61awqm41yr3yde",
    "event_type": "subscription.updated",
    "occurred_at": "2024-01-30T18:34:55.360948Z",
    "notification_id": "ntf_01hndte7mf1dtd6pnhd96rkt0s"
}
EOF;

    return new WebhookHandled(json_decode($json, true));
}
