<?php

namespace Opcodes\Spike;

use Illuminate\Contracts\Support\Arrayable;
use Opcodes\Spike\Contracts\Providable;

class Product implements Arrayable
{
    public function __construct(
        public string  $id,
        public string  $name,
        public ?string $short_description = null,
        public ?array  $features = [],
        public ?string $payment_provider_price_id = null,
        public ?int    $price_in_cents = null,
        public array   $provides = [],
        public bool    $archived = false,
        public ?string $currency = null,
    ) {
        $this->validateProvides();
    }

    public static function fromArray(array $config): static
    {
        if (isset($config['provides'])) {
            $provides = $config['provides'];
        } elseif (isset($config['credits']) && $config['credits'] > 0) {
            $provides = [
                CreditAmount::make($config['credits'])
                    ->expiresAfter($config['expires_after'] ?? null),
            ];
        }

        return new self(
            id: $config['id'],
            name: $config['name'],
            short_description: $config['short_description'] ?? '',
            features: $config['features'] ?? [],
            payment_provider_price_id: $config['payment_provider_price_id'] ?? $config['stripe_price_id'] ?? null,
            price_in_cents: $config['price_in_cents'] ?? 0,
            provides: $provides ?? [],
            archived: $config['archived'] ?? false,
            currency: $config['currency'] ?? null,
        );
    }

    /** Get the formatted price, e.g. "€10,00" */
    public function priceFormatted(): string
    {
        return Utils::formatAmount($this->price_in_cents, $this->currency);
    }

    private function validateProvides()
    {
        foreach ($this->provides as $provides) {
            if (! is_object($provides)) {
                throw new \InvalidArgumentException('Every element inside the "provides" array must be an object.');
            }

            if (! in_array(Providable::class, class_implements($provides))) {
                throw new \InvalidArgumentException('The class ' . get_class($provides) . ' must implement the ' . Providable::class . ' interface.');
            }
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'features' => $this->features,
            'payment_provider_price_id' => $this->payment_provider_price_id,
            'price_in_cents' => $this->price_in_cents,
            'currency' => $this->currency,
            'provides' => $this->provides,
        ];
    }
}
