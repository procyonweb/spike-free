<?php

namespace Opcodes\Spike;

use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Opcodes\Spike\Contracts\CountableProvidable;
use Opcodes\Spike\Contracts\Expirable;
use Opcodes\Spike\Contracts\Providable;
use Opcodes\Spike\Facades\Credits;
use Opcodes\Spike\Traits\ExpirableMethods;

class CreditAmount implements CountableProvidable, Expirable
{
    use ExpirableMethods;

    protected int $amount;
    protected ?CreditType $type;
    protected ?CarbonInterval $expires_after;

    public function __construct(
        int $amount,
        CreditType|string $type,
        ?CarbonInterval $expires_after = null,
    ) {
        $this->setType($type);
        $this->setAmount($amount);
        $this->expiresAfter($expires_after);
    }

    public static function __set_state(array $data): static
    {
        return new static(
            $data['amount'],
            $data['type'],
            isset($data['expires_after'])
                ? CarbonInterval::make($data['expires_after'])
                : null,
        );
    }

    public static function make(int $amount, ?string $type = null, ?CarbonInterval $expires_after = null): self
    {
        return new static($amount, $type ?? CreditType::default(), $expires_after);
    }

    public function key(): string
    {
        return 'credit-amount:' . $this->getType()->type;
    }

    public function name(): string
    {
        return $this->getType()->name(2);
    }

    public function icon(): ?string
    {
        return $this->getType()->icon();
    }

    public function isSameProvidable(Providable $providable): bool
    {
        return $providable instanceof CreditAmount
            && $this->getType()->is($providable->getType())
            && (
                ($this->getExpiresAfter() === null && $providable->getExpiresAfter() === null)
                || $this->getExpiresAfter()->eq($providable->getExpiresAfter())
            );
    }

    public function provideMonthlyFromSubscriptionPlan(SubscriptionPlan $subscriptionPlan, $billable): void
    {
        DB::transaction(function () use ($subscriptionPlan, $billable) {

            $currentSubscriptionTransaction = Credits::billable($billable)
                ->type($this->getType())
                ->currentSubscriptionTransaction();

            CreditTransaction::create([
                'billable_id' => $billable->getKey(),
                'billable_type' => $billable->getMorphClass(),
                'type' => CreditTransaction::TYPE_SUBSCRIPTION,
                'credit_type' => $this->getType()->type,
                'credits' => $this->getAmount(),
                // subscription credits do not expire - they are renewed.
            ]);

            $currentSubscriptionTransaction?->expire();

            Credits::billable($billable)->type($this->getType())->clearCache();

        }, 3);
    }

    public function getLatestCreditTransaction($billable): ?CreditTransaction
    {
        return CreditTransaction::query()
            ->where('billable_id', $billable->getKey())
            ->where('billable_type', $billable->getMorphClass())
            ->where('type', CreditTransaction::TYPE_SUBSCRIPTION)
            ->where('credit_type', $this->getType()->type)
            ->where('credits', $this->getAmount())
            ->latest('id')
            ->first();
    }

    public function provideOnceFromProduct(Product $product, $billable): void
    {
        DB::transaction(function () use ($product, $billable) {
            CreditTransaction::create([
                'billable_id' => $billable->getKey(),
                'billable_type' => $billable->getMorphClass(),
                'type' => CreditTransaction::TYPE_PRODUCT,
                'credit_type' => $this->getType()->type,
                'credits' => $this->getAmount(),
                'expires_at' => $this->getExpiresAfter()
                    ? Carbon::now()->add($this->getExpiresAfter())
                    : null,
            ]);

            Credits::billable($billable)->type($this->getType())->clearCache();
        }, 3);
    }

    public function setAmount(?int $amount = null): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount ?? 0;
    }

    public function setType(CreditType|string|null $type = null): self
    {
        if (is_null($type)) {
            $this->type = null;
        } else {
            $this->type = CreditType::make($type);
        }

        return $this;
    }

    public function getType(): CreditType
    {
        return $this->type ?? CreditType::default();
    }

    public function toString(): string
    {
        $string = number_format($this->getAmount());

        if (isset($this->type)) {
            $string .= ' ' . $this->type->name($this->amount);
        }

        return $string;
    }
}
