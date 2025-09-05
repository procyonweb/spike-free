<?php

namespace Opcodes\Spike\Exceptions;

use Exception;

class MissingPaymentProviderException extends Exception
{
    const DEFAULT_MESSAGE = 'Payment provider has not been set up. Please see Spike documentation and set up a payment provider.';

    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message ?: self::DEFAULT_MESSAGE, $code, $previous);
    }
}
