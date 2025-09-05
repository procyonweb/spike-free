<?php

namespace Opcodes\Spike;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CreditType implements Arrayable
{
    public function __construct(
        public string $type,
    ) {}

    public static function make($type): self
    {
        if (is_string($type)) {
            return new static($type);
        } elseif ($type instanceof self) {
            return $type;
        }

        throw new \InvalidArgumentException('Invalid credit type "'.$type.'". Please make sure it is configured.');
    }

    public static function default(): self
    {
        return new static(self::all()[0]->type ?? 'credits');
    }

    public static function all(): Collection
    {
        return collect(config('spike.credit_types'))
            ->map(fn(array $config) => CreditType::make($config['id']));
    }

    public static function __set_state(array $data): CreditType
    {
        if (isset($data['type'])) {
            return static::make($data['type']);
        }

        return static::default();
    }

    public function config(): array
    {
        return Arr::first(
            config('spike.credit_types', []),
            fn ($config) => $config['id'] === $this->type,
            $this->defaultConfig()
        );
    }

    public function name(int $creditAmount = 2): string
    {
        return trans_choice($this->config()['translation_key'] ?? '', $creditAmount);
    }

    public function is(CreditType|string $type): bool
    {
        return $this->type === CreditType::make($type)->type;
    }

    public function isValid(): bool
    {
        return self::all()->contains('type', $this->type);
    }

    public function icon(): ?string
    {
        return $this->config()['icon'] ?? null;
    }

    public function priceId(): ?string
    {
        return $this->config()['payment_provider_price_id'] ?? null;
    }

    public function shouldChargeNegativeBalance(): bool
    {
        return $this->config()['charge_negative_balances'] ?? true;
    }

    protected function defaultConfig(): array
    {
        return [
            'translation_key' => 'spike::translations.credits',
            'icon' => null,
        ];
    }

    public function toArray()
    {
        return [
            'type' => $this->type,
            'name' => $this->name(),
            'icon' => $this->icon(),
        ];
    }
}
