<?php

namespace Opcodes\Spike\Exceptions;

use Exception;
use JetBrains\PhpStorm\Internal\LanguageLevelTypeAware;

class InvalidSubscriptionPlanException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message ?: 'The subscription plan does not have a valid "payment_provider_price_id" value.', $code, $previous);
    }
}
