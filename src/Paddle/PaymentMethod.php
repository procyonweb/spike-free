<?php

namespace Opcodes\Spike\Paddle;

use Illuminate\Support\Arr;

class PaymentMethod
{
    public function __construct(
        public string $type,
        public ?string $card_type,
        public ?string $card_last_four,
        public ?int $card_expiry_month,
        public ?int $card_expiry_year,
        public ?string $card_holder_name,
    )
    {
    }

    public static function fromPaddleTransaction(array $paddleTransaction): ?self
    {
        $methodDetails = Arr::get($paddleTransaction, 'payments.0.method_details', null);

        if (is_null($methodDetails)) {
            return null;
        }

        return new PaymentMethod(
            type: $methodDetails['type'],
            card_type: Arr::get($methodDetails, 'card.type'),
            card_last_four: Arr::get($methodDetails, 'card.last4'),
            card_expiry_month: Arr::get($methodDetails, 'card.expiry_month'),
            card_expiry_year: Arr::get($methodDetails, 'card.expiry_year'),
            card_holder_name: Arr::get($methodDetails, 'card.cardholder_name'),
        );
    }
}
