<?php

namespace Opcodes\Spike\Http\Livewire;

use Illuminate\Http\Request;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Livewire\Component;
use Opcodes\Spike\Actions\Subscriptions\StripeCheckoutRedirectWithLock;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SubscriptionManager;
use Opcodes\Spike\SubscriptionPlan;

class Subscriptions extends Component
{
    public bool $yearly = false;

    protected ?SubscriptionPlan $planToTrigger = null;

    public function mount(Request $request, ?SubscriptionPlan $preselectedPlan = null)
    {
        $this->planToTrigger = $preselectedPlan;
        $currentPlan = Spike::currentSubscriptionPlan();
        $subscription = Spike::resolve()->subscription();

        if ($request->has('period')) {
            $this->yearly = trim(strtolower($request->query('period'))) === 'yearly';
        } elseif (
            $currentPlan->isFree()
            && ($subscription?->valid() || $subscription?->isPastDue())
            && $newPlan = Spike::findSubscriptionPlan($subscription->stripe_price)
        ) {
            $this->yearly = $newPlan->isYearly();
        } else {
            $this->yearly = $currentPlan->isPaid() && $currentPlan->isYearly();
        }
    }

    public function render()
    {
        $yearly = Spike::yearlySubscriptionPlans();
        $monthly = Spike::monthlySubscriptionPlans();

        if ($this->yearly && $yearly->isNotEmpty()) {
            $subscriptionPlans = $yearly;
            $hasAlternativePeriodPlans = $monthly->isNotEmpty();
        } else {
            $this->yearly = false;
            $subscriptionPlans = $monthly;
            $hasAlternativePeriodPlans = $yearly->isNotEmpty();
        }

        $freePlanExists = $subscriptionPlans->filter->isFree()->isNotEmpty();

        return view('spike::livewire.subscriptions', [
            'subscriptions' => $subscriptionPlans,
            'freePlanExists' => $freePlanExists,
            'hasAlternativePeriodPlans' => $hasAlternativePeriodPlans,
            'currentPlan' => Spike::currentSubscriptionPlan(),
            'hasSubscription' => Spike::resolve()->subscribed(),
            'hasIncompletePayment' => PaymentGateway::hasIncompleteSubscriptionPayment(),
            'planToTrigger' => $this->planToTrigger,
            'cashierSubscription' => Spike::resolve()->subscription(),
        ]);
    }

    public function togglePeriod()
    {
        $this->yearly = !$this->yearly;
    }

    public function subscribeTo($price_id)
    {
        $isSubscribed = Spike::resolve()->isSubscribed();
        $plan = Spike::findSubscriptionPlan($price_id);

        if ($plan?->isPaid() && !$isSubscribed && Spike::paymentProvider()->isStripe() && Spike::stripeCheckoutEnabled()) {
            return app(StripeCheckoutRedirectWithLock::class)->handle($plan);
        }

        return $this->dispatch(
            'openModal',
            component: 'spike::subscribe',
            arguments: ['price_id' => $price_id]
        );
    }

    public function unsubscribe()
    {
        // get the free plan
        $freePlan = Spike::subscriptionPlans()->filter->isFree()->first()
            ?? SubscriptionPlan::defaultFreePlan();

        return $this->subscribeTo($freePlan->payment_provider_price_id);
    }

    public function resumePlan($price_id)
    {
        $subscriptionPlan = Spike::findSubscriptionPlan($price_id);

        try {
            app(SubscriptionManager::class)->subscribeTo($subscriptionPlan);
        } catch (IncompletePayment $exception) {
            return redirect()->route('cashier.payment', [
                $exception->payment->id,
                'redirect' => route('spike.subscribe'),
            ]);
        }
    }
}
