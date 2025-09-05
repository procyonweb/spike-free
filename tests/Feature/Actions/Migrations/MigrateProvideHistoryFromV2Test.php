<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opcodes\Spike\Actions\Migrations\MigrateProvideHistoryFromV2;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\CreditTransaction;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\ProvideHistory;
use Opcodes\Spike\Stripe\Subscription;
use Opcodes\Spike\Stripe\SubscriptionItem;

uses(RefreshDatabase::class);

it('does nothing if there have been no transactions', function () {
    app(MigrateProvideHistoryFromV2::class)->handle();

    $this->assertDatabaseEmpty(ProvideHistory::class);
});

it('migrates product purchases', function () {
    $billable1 = createBillable();
    $billable2 = createBillable();

    $cart1 = Cart::factory()
        ->has(CartItem::factory(), 'items')
        ->forBillable($billable1)
        ->create();
    $cart2 = Cart::factory()
        ->has(CartItem::factory(), 'items')
        ->forBillable($billable2)
        ->create();

    $creditTransaction1 = CreditTransaction::factory()
        ->forBillable($billable1)
        ->product()
        ->create([
            'created_at' => $firstProductCreatedAt = now()->subMonths(3),
            'cart_id' => $cart1->id,
            'cart_item_id' => $cart1->items->first()->id,
        ]);
    $creditTransaction2 = CreditTransaction::factory()
        ->forBillable($billable2)
        ->product()
        ->create([
            'created_at' => $secondProductCreatedAt = now()->subMonths(2),
            'cart_id' => $cart2->id,
            'cart_item_id' => $cart2->items->first()->id,
        ]);

    app(MigrateProvideHistoryFromV2::class)->handle();

    $this->assertDatabaseCount(ProvideHistory::class, 2);
    $this->assertDatabaseHas(ProvideHistory::class, [
        'billable_id' => $billable1->getKey(),
        'billable_type' => $billable1->getMorphClass(),
        'related_item_id' => $cart1->items->first()->getKey(),
        'related_item_type' => $cart1->items->first()->getMorphClass(),
        'providable_key' => CreditAmount::make($creditTransaction1->credits)->key(),
        'providable_data' => serialize(CreditAmount::make($creditTransaction1->credits)),
        'provided_at' => $firstProductCreatedAt,
    ]);
    $this->assertDatabaseHas(ProvideHistory::class, [
        'related_item_id' => $cart2->items->first()->getKey(),
        'related_item_type' => $cart2->items->first()->getMorphClass(),
        'billable_id' => $billable2->getKey(),
        'billable_type' => $billable2->getMorphClass(),
        'providable_key' => CreditAmount::make($creditTransaction2->credits)->key(),
        'providable_data' => serialize(CreditAmount::make($creditTransaction2->credits)),
        'provided_at' => $secondProductCreatedAt,
    ]);
});

it('migrates subscription credits', function () {
    \Opcodes\Spike\Facades\PaymentGateway::fake();
    $billable1 = createBillable();
    $billable2 = createBillable();
    $subscriptionPlan = Spike::monthlySubscriptionPlans()->filter->isPaid()->first();

    $subscription1 = Subscription::factory()
        ->withoutPaymentCard()
        ->for($billable1)
        ->has(SubscriptionItem::factory(['stripe_price' => $subscriptionPlan->payment_provider_price_id]), 'items')
        ->hasItems()
        ->create(['stripe_price' => $subscriptionPlan->payment_provider_price_id, 'renews_at' => $sub1Renewal = now()->subMonths(1)]);
    $subscription1LineItem = SubscriptionItem::find($subscription1->items->first()->id);
    $subscription2 = Subscription::factory()
        ->withoutPaymentCard()
        ->for($billable2)
        ->has(SubscriptionItem::factory(['stripe_price' => $subscriptionPlan->payment_provider_price_id]), 'items')
        ->create(['stripe_price' => $subscriptionPlan->payment_provider_price_id, 'renews_at' => $sub2Renewal = now()->subDays(3)->addMonth()]);
    $subscription2LineItem = SubscriptionItem::find($subscription2->items->first()->id);

    $creditTransaction1 = CreditTransaction::factory()
        ->forBillable($billable1)
        ->subscription()
        ->create([
            'created_at' => $firstSubscriptionCreatedAt = $sub1Renewal->copy()->subMonthNoOverflow(),
            'subscription_item_id' => $subscription1LineItem,
        ]);
    $creditTransaction2 = CreditTransaction::factory()
        ->forBillable($billable2)
        ->subscription()
        ->create([
            'created_at' => $secondSubscriptionCreatedAt = $sub2Renewal->copy()->subMonthNoOverflow(),
            'subscription_item_id' => $subscription2LineItem,
        ]);

    app(MigrateProvideHistoryFromV2::class)->handle();

    $this->assertDatabaseCount(ProvideHistory::class, 2);
    $this->assertDatabaseHas(ProvideHistory::class, [
        'billable_id' => $billable1->getKey(),
        'billable_type' => $billable1->getMorphClass(),
        'related_item_id' => $subscription1LineItem->provideHistoryId(),
        'related_item_type' => $subscription1LineItem->provideHistoryType(),
        'providable_key' => CreditAmount::make($creditTransaction1->credits)->key(),
        'providable_data' => serialize(CreditAmount::make($creditTransaction1->credits)),
        'provided_at' => $firstSubscriptionCreatedAt,
    ]);
    $this->assertDatabaseHas(ProvideHistory::class, [
        'related_item_id' => $subscription2LineItem->provideHistoryId(),
        'related_item_type' => $subscription2LineItem->provideHistoryType(),
        'billable_id' => $billable2->getKey(),
        'billable_type' => $billable2->getMorphClass(),
        'providable_key' => CreditAmount::make($creditTransaction2->credits)->key(),
        'providable_data' => serialize(CreditAmount::make($creditTransaction2->credits)),
        'provided_at' => $secondSubscriptionCreatedAt,
    ]);

    $this->assertFalse(
        ProvideHistory::hasProvidedMonthly(
            $subscription1LineItem,
            CreditAmount::make($creditTransaction1->credits),
            $billable1,
        )
    );
    $this->assertTrue(
        ProvideHistory::hasProvidedMonthly(
            $subscription2LineItem,
            CreditAmount::make($creditTransaction2->credits),
            $billable2,
        )
    );
});
