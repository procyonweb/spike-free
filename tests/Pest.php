<?php

use Illuminate\Support\Facades\Hash;
use Opcodes\Spike\CreditAmount;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\Tests\Fixtures\Stripe\User as StripeUser;
use Opcodes\Spike\Tests\Fixtures\Paddle\User as PaddleUser;
use Opcodes\Spike\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class)->in(__DIR__);

uses()->afterEach(function () {
    Spike::clearCustomResolvers();
    Credits::clearCustomCallbacks();
})->in('Feature', 'Unit');

function loadJsonToArray(string $path)
{
    $path = Str::start($path, '/');
    $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

    return json_decode(file_get_contents(dirname(__DIR__).$path), true);
}

function testBillableClass(): string
{
    return match (Spike::paymentProvider()) {
        PaymentProvider::Paddle => PaddleUser::class,
        default => StripeUser::class,
    };
}

function createBillable($id = null, bool $withEvents = false)
{
    $userClass = testBillableClass();

    $eventDispatcher = $userClass::getEventDispatcher();

    if (! $withEvents) {
        $userClass::unsetEventDispatcher();
    }

    $extraAttributes = match (Spike::paymentProvider()) {
        PaymentProvider::Paddle => [],
        PaymentProvider::Stripe => [
            'stripe_id' => 'cus_' . Str::random(14),
        ],
    };

    $user = $userClass::create(array_merge([
        'id' => $id,
        'name' => 'Test name ' . Str::random(6),
        'email' => Str::random(10).'@example.com',
        'password' => Hash::make('password'),
    ], $extraAttributes));

    if (Spike::paymentProvider() === PaymentProvider::Paddle) {
        \Opcodes\Spike\Paddle\Customer::create([
            'billable_id' => $user->id,
            'billable_type' => $userClass,
            'paddle_id' => 'ctm_' . Str::random(26),
            'name' => $user->paddleName(),
            'email' => $user->paddleEmail(),
        ]);
    }

    $userClass::setEventDispatcher($eventDispatcher);

    return $user;
}

function setupMonthlySubscriptionPlan(array|int $provides, int $price_in_cents = 0, string $payment_provider_price_id = null)
{
    if (is_int($provides)) {
        $provides = [CreditAmount::make($provides)];
    }

    $payment_provider_price_id = $payment_provider_price_id ?? 'price_'.Str::random(16);
    $currentPlans = config('spike.subscriptions');
    $currentPlans[] = [
        'id' => Str::random(10),
        'name' => Str::random(10),
        'provides_monthly' => $provides,
        'payment_provider_price_id_monthly' => $payment_provider_price_id,
        'price_in_cents_monthly' => $price_in_cents,
    ];
    config(['spike.subscriptions' => $currentPlans]);

    return Spike::findSubscriptionPlan($payment_provider_price_id);
}

function setupYearlySubscriptionPlan(array|int $provides, int $price_in_cents = 0, string $payment_provider_price_id = null)
{
    if (is_int($provides)) {
        $provides = [CreditAmount::make($provides)];
    }

    $payment_provider_price_id = $payment_provider_price_id ?? 'price_'.Str::random(16);
    $currentPlans = config('spike.subscriptions');
    $currentPlans[] = [
        'id' => Str::random(10),
        'name' => Str::random(10),
        'provides_monthly' => $provides,
        'payment_provider_price_id_yearly' => $payment_provider_price_id,
        'price_in_cents_yearly' => $price_in_cents,
    ];
    config(['spike.subscriptions' => $currentPlans]);

    return Spike::findSubscriptionPlan($payment_provider_price_id);
}

function createPaddleTransactionWebhookEvent(string $type, array $data): \Laravel\Paddle\Events\WebhookHandled
{
    return new \Laravel\Paddle\Events\WebhookHandled([
        'event_id' => 'evt_01hn2b4g8jk90jkdmzv8x1ajdt',
        'event_type' => $type,
        'occurred_at' => now()->toRfc3339String(),
        'notification_id' => 'ntf_01hn2b4gb6ndwnsx7897bzvyz7',
        'data' => $data,
    ]);
}
