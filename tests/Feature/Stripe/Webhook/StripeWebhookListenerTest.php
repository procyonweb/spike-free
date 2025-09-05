<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookHandled;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\ProvideHistory;
use Opcodes\Spike\Stripe\Listeners\StripeWebhookListener;
use Opcodes\Spike\Stripe\Subscription;

uses(RefreshDatabase::class);

beforeEach(function () {
    PaymentGateway::fake();
    $this->billable = createBillable();
    $this->standardPlan = setupMonthlySubscriptionPlan(30, 100);
    $this->billable->subscribeTo($this->standardPlan);
    Subscription::query()->update(['stripe_id' => $this->subId = 'sub_123']);
    $this->billable->stripe_id = $this->customerId = 'cus_123';
    $this->billable->save();
});

it('does not call Spike::resolve()', function () {
    config(['spike.stripe.checkout.enabled' => true]);
    $resolveCalled = false;

    Spike::resolve(function ($request) use (&$resolveCalled) {
        $resolveCalled = true;
    });

    $event = new WebhookHandled([
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => $this->subId,
                'customer' => $this->customerId,
                'plan' => [
                    'id' => $this->standardPlan->payment_provider_price_id,
                ],
                'status' => 'active',
            ],
        ],
    ]);

    $listener = new StripeWebhookListener();
    $listener->handle($event);

    $this->assertFalse($resolveCalled, 'Spike::resolve() should not be called');
});

it('provides the providables', function () {
    config(['spike.stripe.checkout.enabled' => true]);
    CreditTransaction::truncate();
    ProvideHistory::truncate();
    expect($this->billable->credits()->balance())->toBe(0);

    $event = new WebhookHandled([
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => $this->subId,
                'customer' => $this->customerId,
                'plan' => [
                    'id' => $this->standardPlan->payment_provider_price_id,
                ],
                'status' => 'active',
            ],
        ],
    ]);

    $listener = new StripeWebhookListener();
    $listener->handle($event);
    $this->billable->refresh();

    expect($this->billable->credits()->balance())->toBe(30);

    // running this again will not create another credit transaction
    $listener = new StripeWebhookListener();
    $listener->handle($event);
    $this->billable->refresh();

    expect($this->billable->credits()->balance())->toBe(30);
});
