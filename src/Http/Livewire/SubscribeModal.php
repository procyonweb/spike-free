<?php

namespace Opcodes\Spike\Http\Livewire;

use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Paddle\Exceptions\PaddleException;
use LivewireUI\Modal\ModalComponent;
use Opcodes\Spike\Contracts\Providable;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\SubscriptionManager;
use Stripe\Exception\CardException;
use Opcodes\Spike\Http\Livewire\Traits\HasCancellationOffers;

class SubscribeModal extends ModalComponent
{
    use HasCancellationOffers;

    public bool $shouldLoadPaymentMethod = false;
    public string $price_id;
    public string $discount_code_error = '';
    public bool $cardDeclined = false;

    protected $listeners = [
        'paymentMethodAdded' => '$refresh',
    ];

    public function mount(string $price_id)
    {
        $this->price_id = $price_id;
        $billable = Spike::resolve();

        if ($billable->subscription()?->hasPromotionCode() && ! $billable->stripePromotionCode()) {
            // The existing plan had a promotion code applied. If we should keep it, let's add it to the session again.
            if (Spike::stripePersistDiscountsWhenSwitchingPlans()) {
                $billable->usePromotionCode($billable->subscription()->promotionCode());
            }
        }
    }

    public function render()
    {
        $billable = Spike::resolve();
        $plan = Spike::findSubscriptionPlan($this->price_id);
        $paymentMethod = $this->shouldLoadPaymentMethod && $plan->isPaid() ? $billable->defaultPaymentMethod() : null;
        $currentPlan = Spike::currentSubscriptionPlan();

        if ($currentPlan->isPaid() && $billable->subscription()->hasPromotionCode()) {
            $currentPlan = $currentPlan->withPromotionCode($billable->stripePromotionCode());
        }

        if ($plan->isFree() && ! isset($this->cancellationOffers)) {
            $this->advanceCancellationOffer();
        }

        $provideDifferences = $this->getProvidableDifferences($currentPlan, $plan);
        $isStripe = Spike::paymentProvider()->isStripe();
        $isPaddle = Spike::paymentProvider()->isPaddle();

        return view('spike::livewire.subscribe-modal', [
            'isStripe' => $isStripe,
            'isPaddle' => $isPaddle,
            'paymentMethod' => $paymentMethod,
            'plan' => $plan->withPromotionCode($billable->stripePromotionCode()),
            'currentPlan' => $currentPlan,
            'provideDifferences' => $provideDifferences,
            'hasSubscription' => $billable->subscribed(),
            'canSubscribe' => $this->canSubscribe($plan, $paymentMethod),
            'allowDiscountCodes' => $isStripe && Spike::stripeAllowDiscounts(),
            'promotionCode' => $billable->stripePromotionCode(),
        ]);
    }

    protected function canSubscribe($plan, $paymentMethod): bool
    {
        if ($this->cardDeclined) {
            return false;
        }

        return $plan->isFree()
            || ($plan->isPaid() && Spike::paymentProvider()->isStripe() && $paymentMethod)
            || ($plan->isPaid() && Spike::paymentProvider()->isPaddle());
    }

    public function loadPaymentMethod()
    {
        $this->shouldLoadPaymentMethod = true;
    }

    /**
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     * @throws \Laravel\Cashier\Exceptions\IncompletePayment
     */
    public function subscribe()
    {
        $subscriptionPlan = Spike::findSubscriptionPlan($this->price_id);

        try {
            app(SubscriptionManager::class)->subscribeTo($subscriptionPlan);
        } catch (IncompletePayment $exception) {
            return redirect()->route('cashier.payment', [
                $exception->payment->id,
                'redirect' => route('spike.subscribe'),
            ]);
        } catch (CardException $exception) {
            $this->cardDeclined = true;
            return null;
        } catch (PaddleException $exception) {
            if (
                str_contains($exception->getMessage(), 'Unable to charge')
                || str_contains($exception->getMessage(), 'payment declined')
            ) {
                $this->cardDeclined = true;
                return null;
            }

            throw $exception;
        }

        Spike::resolve()->removeStripePromotionCode();

        return to_route('spike.subscribe');
    }

    public function addDiscountCode(string $discountCode)
    {
        $this->discount_code_error = '';

        if (empty(trim($discountCode))) {
            $this->discount_code_error = __('spike::translations.discount_code_invalid');
            return;
        }

        $billable = Spike::resolve();
        $promotionCode = $billable->findActivePromotionCode($discountCode);

        if (! $promotionCode) {
            $this->discount_code_error = __('spike::translations.discount_code_invalid');
            return;
        }

        if (! $promotionCode->active) {
            $this->discount_code_error = __('spike::translations.discount_code_inactive');
            return;
        }

        $billable->usePromotionCode($promotionCode);
    }

    public function removeDiscountCode()
    {
        $billable = Spike::resolve();
        $billable->removeStripePromotionCode();
    }

    public static function closeModalOnClickAway(): bool
    {
        return false;
    }

    public static function closeModalOnEscape(): bool
    {
        return false;
    }

    public static function destroyOnClose(): bool
    {
        return false;
    }

    public static function modalMaxWidth(): string
    {
        return 'xl';
    }

    private function getProvidableDifferences(?\Opcodes\Spike\SubscriptionPlan $currentPlan, ?\Opcodes\Spike\SubscriptionPlan $plan): array
    {
        if (! $currentPlan && ! $plan) {
            return [];

        } elseif (! $currentPlan) {
            return array_map(function ($provide) {
                /** @var Providable $provide */
                return [
                    'name' => $provide->name(),
                    'old' => null,
                    'new' => $provide
                ];
            }, $plan->provides_monthly);
        }

        $oldProvides = collect($currentPlan->provides_monthly);
        $newProvides = collect($plan->provides_monthly);

        $removedProvides = $oldProvides->filter(function ($oldProvide) use ($newProvides) {
            return ! $newProvides->contains(fn($newProvide) => $oldProvide->isSameProvidable($newProvide));
        });

        return $newProvides
            ->map(function ($newProvide) use ($currentPlan) {
                $oldProvide = collect($currentPlan->provides_monthly)
                    ->first(fn($oldProvide) => $oldProvide->isSameProvidable($newProvide));

                /** @var Providable $newProvide */
                return [
                    'name' => $newProvide->name(),
                    'old' => $oldProvide,
                    'new' => $newProvide
                ];
            })
            ->merge($removedProvides->map(function ($removedProvide) {
                /** @var Providable $removedProvide */
                return [
                    'name' => $removedProvide->name(),
                    'old' => $removedProvide,
                    'new' => null
                ];
            }))
            ->toArray();
    }
}
