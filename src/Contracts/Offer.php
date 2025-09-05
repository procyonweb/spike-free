<?php

namespace Opcodes\Spike\Contracts;

use Opcodes\Spike\SubscriptionPlan;

interface Offer
{
    /**
     * Method necessary to implement if you want to cache Laravel config.
     * Great explanation with examples here: https://stackoverflow.com/a/46442317
     */
    public static function __set_state(array $data): Offer;

    public function identifier(): string;

    public function name(): string;

    public function description(): string;

    public function view(): ?string;

    public function isAvailableFor(SubscriptionPlan $plan, mixed $billable): bool;

    public function apply(mixed $billable): void;
}
