<?php

namespace Opcodes\Spike\Contracts;

use Illuminate\Database\Eloquent\Model;
use Opcodes\Spike\Product;
use Opcodes\Spike\SubscriptionPlan;

interface Providable
{
    public static function __set_state(array $data): Providable;

    /**
     * Uniqueness key for the providable.
     * Used to determine whether this providable has been provided already.
     */
    public function key(): string;

    /**
     * Get the name of the providable.
     */
    public function name(): string;

    /**
     * The URL to the icon representing the providable. Used on the frontend.
     */
    public function icon(): ?string;

    /**
     * The string representation of the providable, to be displayed on the frontend.
     */
    public function toString(): string;

    /**
     * Find whether the product is the same as the provided product.
     */
    public function isSameProvidable(Providable $providable): bool;

    /**
     * Provide the benefits to the billable.
     *
     * @param Product $product
     * @param SpikeBillable|Model $billable
     * @return void
     */
    public function provideOnceFromProduct(Product $product, $billable): void;

    /**
     * @param SubscriptionPlan $subscriptionPlan
     * @param SpikeBillable|Model $billable
     * @return void
     */
    public function provideMonthlyFromSubscriptionPlan(SubscriptionPlan $subscriptionPlan, $billable): void;
}
