<?php

namespace Opcodes\Spike;

use Carbon\CarbonInterface;
use Laravel\Cashier\PaymentMethod;
use Opcodes\Spike\Facades\Spike;

class Utils
{
    public static function subMonthRelatedToOriginalDate(CarbonInterface $date, CarbonInterface $originalDate): CarbonInterface
    {
        $newDate = $date->copy()->subMonthNoOverflow();

        if ($newDate->day < $originalDate->day) {
            // e.g. 30 vs 31, or 28 vs 30. Let's add as many as available.
            $newDate = $newDate->addDays(
                min($newDate->daysInMonth, $originalDate->day) - $newDate->day
            );
        }

        return $newDate;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @param  string|null  $currency
     * @param  string|null  $locale
     * @param  array  $options
     * @return string
     */
    public static function formatAmount($amount, $currency = null, $locale = null, array $options = [])
    {
        $paymentProvider = Spike::paymentProvider();

        if ($paymentProvider === PaymentProvider::Stripe) {
            return \Laravel\Cashier\Cashier::formatAmount($amount, $currency, $locale, $options);
        }

        if ($paymentProvider === PaymentProvider::Paddle) {
            $defaultCurrency = config('spike.currency', config('cashier.currency'));

            return \Laravel\Paddle\Cashier::formatAmount($amount, $currency ?? $defaultCurrency, $locale, $options);
        }

        return ($currency ? $currency . ' ' : '') . number_format($amount, 2);
    }

    public static function paymentMethodName(PaymentMethod|array $paymentMethod): string
    {
        if ($paymentMethod instanceof PaymentMethod) {
            $paymentMethod = $paymentMethod->toArray();
        }

        return match($paymentMethod['type']) {
            'card' => __('spike::translations.card_ending', [
                'card_brand' => ucfirst($paymentMethod['card']['brand']),
                'last_four' => $paymentMethod['card']['last4']
            ]),
            'sepa_debit' => "SEPA Debit ending in {$paymentMethod['sepa_debit']['last4']}",
            'bacs_debit' => "BACS Debit ending in {$paymentMethod['bacs_debit']['last4']}",
            'ideal' => "iDEAL ({$paymentMethod['ideal']['bank']})",
            'sofort' => "SOFORT ({$paymentMethod['sofort']['country']})",
            'eps' => "EPS ({$paymentMethod['eps']['bank']})",
            'fpx' => "FPX ({$paymentMethod['fpx']['bank']})",
            default => ucfirst(str_replace('_', ' ', $paymentMethod['type']))
        };
    }
}
