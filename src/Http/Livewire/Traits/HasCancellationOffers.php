<?php

namespace Opcodes\Spike\Http\Livewire\Traits;

use Opcodes\Spike\Contracts\Offer;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\Stripe\OfferService;

trait HasCancellationOffers
{
    public array $cancellationOffers;
    public array $currentCancellationOffer = [];
    public bool $cancellationOfferAccepted = false;

    private function advanceCancellationOffer(): void
    {
        if (! Spike::paymentProvider()->supportsCancellationOffers()) {
            return;
        }

        if (! isset($this->cancellationOffers)) {
            $this->cancellationOffers = app(OfferService::class)
                ->getAvailableOffers(Spike::resolve())
                ->map(function (Offer $offer) {
                    return [
                        'identifier' => $offer->identifier(),
                        'name' => $offer->name(),
                        'description' => $offer->description(),
                        'view' => $offer->view(),
                    ];
                })
                ->all();
        }

        $nextOffer = array_shift($this->cancellationOffers);

        if (empty($nextOffer)) {
            $this->currentCancellationOffer = [];
        } else {
            $this->currentCancellationOffer = $nextOffer;
        }
    }

    public function acceptCancellationOffer(): void
    {
        if (! empty($this->currentCancellationOffer)) {
            $offer = app(OfferService::class)->findOffer($this->currentCancellationOffer['identifier']);

            $offer?->apply(Spike::resolve());

            $this->currentCancellationOffer = [];
            $this->cancellationOffers = [];
            $this->cancellationOfferAccepted = true;
        }
    }

    public function declineCancellationOffer(): void
    {
        // Small delay for better UX
        sleep(1);

        $this->advanceCancellationOffer();
    }
}
