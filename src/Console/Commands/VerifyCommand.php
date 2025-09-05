<?php

namespace Opcodes\Spike\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Opcodes\Spike\CreditType;
use Opcodes\Spike\Exceptions\MissingPaymentProviderException;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\PaymentProvider;
use Opcodes\Spike\Product;
use Opcodes\Spike\SubscriptionPlan;

class VerifyCommand extends Command
{
    protected $signature = 'spike:verify';

    protected $description = 'Verify Stripe/Paddle configuration for products and subscriptions.';

    /** @var Collection|\Stripe\Price[] */
    protected Collection $stripePrices;

    protected Collection $paddlePrices;

    protected string $appCurrency;

    protected array $priceIdsChecked = [];

    protected bool $hasErrors = false;

    public function handle(): int
    {
        match (Spike::paymentProvider()) {
            PaymentProvider::Stripe => $this->verifyStripe(),
            PaymentProvider::Paddle => $this->verifyPaddle(),
            default => throw new MissingPaymentProviderException(),
        };

        $this->verifyCreditConfiguration();

        return $this->hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    protected function verifyStripe(): void
    {
        $this->appCurrency = strtoupper(config('cashier.currency'));
        $this->info('Fetching prices from Stripe...');
        $this->stripePrices = collect(\Laravel\Cashier\Cashier::stripe()->prices->all()->data);

        $billableModels = config('spike.billable_models');

        foreach ($billableModels as $billableModel) {
            $this->verifyStripeProducts($billableModel);
            $this->verifyStripeSubscriptions($billableModel);
        }
    }

    protected function verifyPaddle(): void
    {
        $this->appCurrency = strtoupper(config('cashier.currency'));
        $this->info('Fetching prices from Paddle...');
        $this->paddlePrices = \Opcodes\Spike\Paddle\PaymentGateway::allPrices();

        $billableModels = config('spike.billable_models');

        foreach ($billableModels as $billableModel) {
            $this->verifyPaddleProducts($billableModel);
            $this->verifyPaddleSubscriptions($billableModel);
        }
    }

    protected function verifyStripeProducts($billableClass): void
    {
        $products = Spike::products(new $billableClass);
        $this->newLine();
        $this->info("Verifying products for {$billableClass}...");

        foreach ($products as $product) {
            // Empty price ID
            if (empty($product->payment_provider_price_id)) {
                $this->productError($product, 'Missing price ID');
                continue;
            }

            // Duplicate price ID
            if (in_array($product->payment_provider_price_id, $this->priceIdsChecked)) {
                $this->productError($product, 'Duplicate price ID');
                continue;
            }

            /** @var \Stripe\Price|null $stripePrice */
            $stripePrice = $this->stripePrices->firstWhere('id', $product->payment_provider_price_id);

            // Missing in Stripe price ID
            if (! $stripePrice) {
                $this->productError($product, 'Missing in Stripe');
                continue;
            }

            // archived price
            if (!$stripePrice->active) {
                $this->productError($product, 'Price is archived in Stripe');
                continue;
            }

            // Currency is different
            if (strtoupper($stripePrice->currency) !== $this->appCurrency) {
                $stripeCurrency = strtoupper($stripePrice->currency);
                $this->productError($product, 'Currency mismatch', "({$stripeCurrency} in Stripe, expected {$this->appCurrency})");
                continue;
            }

            // Price is different
            if ($stripePrice->unit_amount !== $product->price_in_cents) {
                $stripePriceFormatted = $this->appCurrency . ' ' . number_format($stripePrice->unit_amount / 100, 2);
                $expectedPriceFormatted = $this->appCurrency . ' ' . number_format($product->price_in_cents / 100, 2);
                $this->productError($product, 'Price mismatch', "(<options=bold>{$stripePriceFormatted}</> in Stripe, expected <options=bold>{$expectedPriceFormatted}</>)");
                continue;
            }

            if ($stripePrice->type !== 'one_time') {
                $this->productError($product, 'Price type mismatch', "(<options=bold>{$stripePrice->type}</> in Stripe, expected <options=bold>one_time</>)");
                continue;
            }

            $this->productOk($product);
        }
    }

    protected function verifyStripeSubscriptions($billableClass): void
    {
        $subscriptions = Spike::subscriptionPlans(new $billableClass);
        $this->newLine();
        $this->info("Verifying subscriptions for {$billableClass}...");

        foreach ($subscriptions as $subscription) {
            // Free plans don't need to be set up in Stripe
            if ($subscription->isFree()) continue;

            // Empty price ID
            if (empty($subscription->payment_provider_price_id)) {
                $this->subscriptionError($subscription, 'Missing price ID');
                continue;
            }

            // Duplicate price ID
            if (in_array($subscription->payment_provider_price_id, $this->priceIdsChecked)) {
                $this->subscriptionError($subscription, 'Duplicate price ID');
                continue;
            }

            /** @var \Stripe\Price|null $stripePrice */
            $stripePrice = $this->stripePrices->firstWhere('id', $subscription->payment_provider_price_id);

            // Missing in Stripe price ID
            if (!$stripePrice) {
                $this->subscriptionError($subscription, 'Missing in Stripe');
                continue;
            }

            // archived price
            if (!$stripePrice->active) {
                $this->subscriptionError($subscription, 'Price is archived in Stripe');
                continue;
            }

            // Currency is different
            if (strtoupper($stripePrice->currency) !== $this->appCurrency) {
                $stripeCurrency = strtoupper($stripePrice->currency);
                $this->subscriptionError($subscription, 'Currency mismatch', "(<options=bold>{$stripeCurrency}</> in Stripe, expected <options=bold>{$this->appCurrency}</>)");
                continue;
            }

            // Price is different
            if ($stripePrice->unit_amount !== $subscription->price_in_cents) {
                $stripePriceFormatted = $this->appCurrency . ' ' . number_format($stripePrice->unit_amount / 100, 2);
                $expectedPriceFormatted = $this->appCurrency . ' ' . number_format($subscription->price_in_cents / 100, 2);
                $this->subscriptionError($subscription, 'Price mismatch', "(<options=bold>{$stripePriceFormatted}</> in Stripe, expected <options=bold>{$expectedPriceFormatted}</>)");
                continue;
            }

            // Stripe price is not a recurring payment
            if ($stripePrice->type !== ($expectedPriceType = \Stripe\Price::TYPE_RECURRING)) {
                $this->subscriptionError($subscription, 'Price type mismatch', "(<options=bold>{$stripePrice->type}</> in Stripe, expected <options=bold>{$expectedPriceType}</>)");
                continue;
            }

            if ($subscription->isMonthly() && ($stripePrice->recurring?->interval !== 'month' || $stripePrice->recurring?->interval_count !== 1)) {
                $this->subscriptionError($subscription, 'Interval mismatch', "(<options=bold>{$stripePrice->recurring->interval_count} {$stripePrice->recurring->interval}</> in Stripe, expected <options=bold>1 month</>)");
                continue;
            } elseif ($subscription->isYearly() && ($stripePrice->recurring?->interval !== 'year' || $stripePrice->recurring?->interval_count !== 1)) {
                $this->subscriptionError($subscription, 'Interval mismatch', "(<options=bold>{$stripePrice->recurring->interval_count} {$stripePrice->recurring->interval}</> in Stripe, expected <options=bold>1 year</>)");
                continue;
            }

            $this->subscriptionOk($subscription);
        }
    }

    protected function verifyPaddleProducts($billableClass)
    {
        $products = Spike::products(new $billableClass);
        $this->newLine();
        $this->info("Verifying products for {$billableClass}...");

        foreach ($products as $product) {
            // Empty price ID
            if (empty($product->payment_provider_price_id)) {
                $this->productError($product, 'Missing price ID');
                continue;
            }

            // Duplicate price ID
            if (in_array($product->payment_provider_price_id, $this->priceIdsChecked)) {
                $this->productError($product, 'Duplicate price ID');
                continue;
            }

            $paddlePrice = $this->paddlePrices->firstWhere('id', $product->payment_provider_price_id);

            // Missing in Paddle price ID
            if (! $paddlePrice) {
                $this->productError($product, 'Missing in Paddle');
                continue;
            }

            // archived price
            if ($paddlePrice['status'] !== 'active') {
                $this->productError($product, 'Price is not active in Paddle');
                continue;
            }

            // Currency is different (maybe we don't need to check for currencies?)
            // $paddleCurrency = strtoupper($paddlePrice['unit_price']['currency_code']);
            // if ($paddleCurrency !== $this->appCurrency) {
            //     $this->productError($product, 'Currency mismatch', "({$paddleCurrency} in Paddle, expected {$this->appCurrency})");
            //     continue;
            // }

            // Price is different
            $paddlePriceInCents = intval($paddlePrice['unit_price']['amount']);
            if ($paddlePriceInCents !== $product->price_in_cents) {
                $paddlePriceFormatted = $this->appCurrency . ' ' . number_format($paddlePriceInCents / 100, 2);
                $expectedPriceFormatted = $this->appCurrency . ' ' . number_format($product->price_in_cents / 100, 2);
                $this->productError($product, 'Price mismatch', "(<options=bold>{$paddlePriceFormatted}</> in Paddle, expected <options=bold>{$expectedPriceFormatted}</>)");
                continue;
            }

            if (isset($paddlePrice['billing_cycle']['interval'])) {
                $this->productError($product, 'Price type mismatch', "(<options=bold>recurring</> price in Paddle, expected <options=bold>one time</> price)");
                continue;
            }

            $this->productOk($product);
        }
    }

    protected function verifyPaddleSubscriptions($billableClass)
    {
        $subscriptions = Spike::subscriptionPlans(new $billableClass);
        $this->newLine();
        $this->info("Verifying subscriptions for {$billableClass}...");

        foreach ($subscriptions as $subscription) {
            // Free plans don't need to be set up in Paddle
            if ($subscription->isFree()) continue;

            // Empty price ID
            if (empty($subscription->payment_provider_price_id)) {
                $this->subscriptionError($subscription, 'Missing price ID');
                continue;
            }

            // Duplicate price ID
            if (in_array($subscription->payment_provider_price_id, $this->priceIdsChecked)) {
                $this->subscriptionError($subscription, 'Duplicate price ID');
                continue;
            }

            $paddlePrice = $this->paddlePrices->firstWhere('id', $subscription->payment_provider_price_id);

            // Missing in Stripe price ID
            if (!$paddlePrice) {
                $this->subscriptionError($subscription, 'Missing in Paddle');
                continue;
            }

            // archived price
            if ($paddlePrice['status'] !== 'active') {
                $this->subscriptionError($subscription, 'Price is not active in Paddle');
                continue;
            }

            // // Currency is different (maybe we don't need to check currency?)
            // $paddleCurrency = strtoupper($paddlePrice['unit_price']['currency_code']);
            // if ($paddleCurrency !== $this->appCurrency) {
            //     $this->subscriptionError($subscription, 'Currency mismatch', "(<options=bold>{$paddleCurrency}</> in Paddle, expected <options=bold>{$this->appCurrency}</>)");
            //     continue;
            // }

            // Price is different
            $paddlePriceInCents = intval($paddlePrice['unit_price']['amount']);
            if ($paddlePriceInCents !== $subscription->price_in_cents) {
                $paddlePriceFormatted = $this->appCurrency . ' ' . number_format($paddlePriceInCents / 100, 2);
                $expectedPriceFormatted = $this->appCurrency . ' ' . number_format($subscription->price_in_cents / 100, 2);
                $this->subscriptionError($subscription, 'Price mismatch', "(<options=bold>{$paddlePriceFormatted}</> in Paddle, expected <options=bold>{$expectedPriceFormatted}</>)");
                continue;
            }

            // Price is not a recurring payment
            if (! isset($paddlePrice['billing_cycle']['interval'])) {
                $this->subscriptionError($subscription, 'Price type mismatch', "(<options=bold>one time</> price in Paddle, expected <options=bold>recurring</> price)");
                continue;
            }

            if ($subscription->isMonthly() && ($paddlePrice['billing_cycle']['interval'] !== 'month' || $paddlePrice['billing_cycle']['frequency'] !== 1)) {
                $this->subscriptionError($subscription, 'Interval mismatch', "(<options=bold>{$paddlePrice['billing_cycle']['frequency']} {$paddlePrice['billing_cycle']['interval']}</> in Paddle, expected <options=bold>1 month</>)");
                continue;
            } elseif ($subscription->isYearly() && ($paddlePrice['billing_cycle']['interval'] !== 'year' || $paddlePrice['billing_cycle']['frequency'] !== 1)) {
                $this->subscriptionError($subscription, 'Interval mismatch', "(<options=bold>{$paddlePrice['billing_cycle']['frequency']} {$paddlePrice['billing_cycle']['interval']}</> in Paddle, expected <options=bold>1 year</>)");
                continue;
            }

            $this->subscriptionOk($subscription);
        }
    }

    protected function productError(Product $product, string $message, string $extra = ''): void
    {
        $this->line("· <options=bold;fg=red>[ERR]</> Product \"{$product->name}\" ({$product->payment_provider_price_id}): <options=bold;fg=red>{$message}</> {$extra}");
        $this->hasErrors = true;
    }

    protected function productOk(Product $product): void
    {
        $this->line("· <options=bold;fg=green>[OK]</> Product \"{$product->name}\" ({$product->payment_provider_price_id})");
        $this->priceIdsChecked[] = $product->payment_provider_price_id;
    }

    protected function subscriptionError(SubscriptionPlan $subscription, string $message, string $extra = ''): void
    {
        $this->line("· <options=bold;fg=red>[ERR]</> Subscription \"{$subscription->name}\" ({$subscription->period}, {$subscription->payment_provider_price_id}): <options=bold;fg=red>{$message}</> {$extra}");
        $this->hasErrors = true;
    }

    protected function subscriptionOk(SubscriptionPlan $subscription): void
    {
        $this->line("· <options=bold;fg=green>[OK]</> Subscription \"{$subscription->name}\" ({$subscription->period}, {$subscription->payment_provider_price_id})");
        $this->priceIdsChecked[] = $subscription->payment_provider_price_id;
    }

    protected function verifyCreditConfiguration(): void
    {
        $creditTypes = CreditType::all();

        if ($creditTypes->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->info("Verifying credit types...");

        /** @var CreditType $creditType */
        foreach ($creditTypes as $creditType) {
            $allowsNegativeBalance = Credits::type($creditType)->isNegativeBalanceAllowed();

            if ($allowsNegativeBalance && Spike::paymentProvider()->isPaddle()) {
                $this->line("· <options=bold;fg=yellow>[WARN]</> Credit type \"{$creditType->type}\": This type allows for negative balance, but Paddle payment provider does not support offline charges. <options=bold;fg=yellow>Negative balances will not be paid for.</>");
                continue;
            }

            if ($allowsNegativeBalance && ! $creditType->priceId()) {
                $this->creditTypeError($creditType, 'Missing price ID', 'Negative balances will not be paid for, please provide "payment_provider_price_id".');
                continue;
            }

            if ($allowsNegativeBalance && $creditType->priceId()) {
                /** @var \Stripe\Price|null $stripePrice */
                $stripePrice = $this->stripePrices->firstWhere('id', $creditType->priceId());

                // Missing in Stripe price ID
                if (! $stripePrice) {
                    $this->creditTypeError($creditType, 'Price ID missing in Stripe');
                    continue;
                }

                // archived price
                if (!$stripePrice->active) {
                    $this->creditTypeError($creditType, 'Price is archived in Stripe');
                    continue;
                }

                // Currency is different
                if (strtoupper($stripePrice->currency) !== $this->appCurrency) {
                    $stripeCurrency = strtoupper($stripePrice->currency);
                    $this->creditTypeError($creditType, 'Currency mismatch', "({$stripeCurrency} in Stripe, expected {$this->appCurrency})");
                    continue;
                }

                if ($stripePrice->type !== 'one_time') {
                    $this->creditTypeError($creditType, 'Price type mismatch', "(<options=bold>{$stripePrice->type}</> in Stripe, expected <options=bold>one_time</>)");
                    continue;
                }
            }

            $this->line("· <options=bold;fg=green>[OK]</> Credit type \"{$creditType->type}\"");
        }
    }

    protected function creditTypeError(CreditType $creditType, string $message, string $extra = ''): void
    {
        $allowsNegative = Credits::type($creditType)->isNegativeBalanceAllowed() ? 'true' : 'false';

        $this->line("· <options=bold;fg=red>[ERR]</> Credit type \"{$creditType->type}\" (allowsNegative: $allowsNegative, priceId: {$creditType->priceId()}): <options=bold;fg=red>{$message}</> {$extra}");
        $this->hasErrors = true;
    }
}
