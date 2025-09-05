<?php

namespace Opcodes\Spike\Http\Controllers;

use Illuminate\Http\Request;
use Opcodes\Spike\Actions\Subscriptions\StripeCheckoutRedirectWithLock;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SubscriptionPlan;

class SubscribeController
{
    public function index(Request $request)
    {
        if (Spike::subscriptionPlans()->isEmpty()) {
            return redirect(route('spike.usage'));
        }

        $preselectedPlan = $this->getSelectedPlanFromQuery($request);

        if (Spike::paymentProvider()->isStripe() && $preselectedPlan && Spike::stripeCheckoutEnabled()) {
            return app(StripeCheckoutRedirectWithLock::class)->handle($preselectedPlan);
        }

        if ($request->boolean('success')) {
            // we'll only reach this place for the first subscription. Changing plans or resuming subs won't trigger this.

            // Let's wait up to 10 seconds before we've received the webhook and the subscription is active
            $counter = 0;
            do {
                sleep(1);
                $subscription = Spike::resolve()->fresh()->getSubscription();
                $counter++;
            } while ((!$subscription || !$subscription->active()) && $counter <= 10);

            // figure out how to redirect
            list('url' => $url, 'delay' => $delay) = Spike::getRedirectAfterSubscriptionTo();

            if (is_callable($url)) {
                $url = $url(Spike::currentSubscriptionPlan());
            }

            if (!empty($url) && $delay <= 0) {
                return redirect($url);
            }
        }

        return view('spike::subscribe', [
            'preselectedPlan' => $preselectedPlan ?? null,
            'subscription' => Spike::resolve()->getSubscription(),
            'validate' => $request->boolean('validate'),
            'success' => $request->boolean('success'),
            'canceled' => $request->boolean('canceled'),
            'redirect_to' => $url ?? null,
            'redirect_delay' => $delay ?? null,
        ]);
    }

    public function incompletePayment()
    {
        if (PaymentGateway::hasIncompleteSubscriptionPayment()) {
            $latestPayment = PaymentGateway::latestSubscriptionPayment();

            if ($latestPayment->requiresPaymentMethod() || $latestPayment->requiresConfirmation() || $latestPayment->requiresAction()) {
                return redirect()->route('cashier.payment', [
                    $latestPayment->id,
                    'redirect' => route('spike.subscribe'),
                ]);
            }
        }

        return redirect()->route('spike.subscribe');
    }

    private function getSelectedPlanFromQuery($request): ?SubscriptionPlan
    {
        $preselectedPlan = null;
        $selectedYearly = trim(strtolower($request->query('period', ''))) === SubscriptionPlan::PERIOD_YEARLY;

        if ($planName = $request->query('plan')) {
            $preselectedPlan = Spike::subscriptionPlans()
                ->filter(function (SubscriptionPlan $plan) use ($selectedYearly) {
                    return $selectedYearly ? $plan->isYearly() : $plan->isMonthly();
                })
                ->firstWhere('id', $planName);
        }

        return $preselectedPlan;
    }
}
