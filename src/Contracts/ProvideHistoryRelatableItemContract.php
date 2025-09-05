<?php

namespace Opcodes\Spike\Contracts;

interface ProvideHistoryRelatableItemContract
{
    public function provideHistoryId(): string;
    public function provideHistoryType(): string;
}
