<?php

namespace Opcodes\Spike\Traits;

trait ManagesNegativeBalanceAllowance
{
    protected static mixed $negativeBalanceCallback;

    protected static array $negativeBalanceTypeCallbacks = [];

    public function allowNegativeBalance(mixed $callback = null): void
    {
        if (is_null($callback)) {
            $callback = fn() => true;
        } elseif (is_bool($callback)) {
            $callback = fn() => $callback;
        }

        if (isset($this->creditType)) {
            self::$negativeBalanceTypeCallbacks[$this->creditType->type] = $callback;
        } else {
            self::$negativeBalanceCallback = $callback;
        }
    }

    public function isNegativeBalanceAllowed(): bool
    {
        if (isset($this->creditType) && isset(self::$negativeBalanceTypeCallbacks[$this->creditType->type])) {
            return call_user_func(
                self::$negativeBalanceTypeCallbacks[$this->creditType->type],
                $this->creditType,
                $this->getBillable(),
            );
        }

        if (isset(self::$negativeBalanceCallback)) {
            return call_user_func(
                self::$negativeBalanceCallback,
                $this->getCreditType(),
                $this->getBillable(),
            );
        }

        return false;
    }
}
