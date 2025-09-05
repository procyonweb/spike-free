<?php

namespace Opcodes\Spike\Contracts;

interface SpikeSubscriptionItem extends ProvideHistoryRelatableItemContract
{
    public function getPriceId(): string;
}
