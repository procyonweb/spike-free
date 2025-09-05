<?php

use Laravel\Cashier\PaymentMethod;
use Stripe\PaymentMethod as StripePaymentMethod;
use Illuminate\Support\Facades\View;

test('it renders the component correctly', function (array $paymentMethodData, array $expectedStrings = []) {
    $billable = createBillable();
    $billable->stripe_id = 'cus_123456789';
    $stripePaymentMethod = StripePaymentMethod::constructFrom(array_merge($paymentMethodData, [
        'customer' => $billable->stripe_id,
    ]));
    $cashierPaymentMethod = new PaymentMethod($billable, $stripePaymentMethod);

    $view = View::make('spike::components.shop.stripe-payment-method-info', [
        'paymentMethod' => $cashierPaymentMethod->toArray(),
    ])->render();

    foreach ($expectedStrings as $expectedString) {
        expect($view)->toContain($expectedString);
    }
})->with('paymentMethods');

dataset('paymentMethods', dataset: [
    "card" => [
        'paymentMethodData' => [
            "id" => "pm_1234567890",
            "object" => "payment_method",
            "type" => "card",
            "card" => [
                "brand" => "visa",
                "exp_month" => 12,
                "exp_year" => 2025,
                "last4" => "4242",
                "funding" => "credit"
            ],
            "billing_details" => [
                "address" => [
                    "city" => "San Francisco",
                    "country" => "US",
                    "postal_code" => "94107"
                ]
            ]
        ],
        'expectedStrings' => [
            'Visa ending in 4242',
            'Expires on December, 2025'
        ],
    ],
    "sepa_debit" => [
        'paymentMethodData' => [
            "id" => "pm_1234567891",
            "object" => "payment_method",
            "type" => "sepa_debit",
            "sepa_debit" => [
                "bank_code" => "37040044",
                "branch_code" => null,
                "country" => "DE",
                "fingerprint" => "uvNYdF7xxxxxxxxx",
                "last4" => "3000"
            ],
            "billing_details" => [
                "name" => "Jenny Rosen",
                "email" => "jenny@example.com"
            ]
        ],
        'expectedStrings' => [
            'SEPA Debit ending in 3000',
        ],
    ],
    "bacs_debit" => [
        'paymentMethodData' => [
            "id" => "pm_1234567892",
            "object" => "payment_method",
            "type" => "bacs_debit",
            "bacs_debit" => [
                "fingerprint" => "wmQzbF7xxxxxxxxx",
                "last4" => "2345",
                "sort_code" => "108800"
            ]
        ],
        'expectedStrings' => [
            'BACS Debit ending in 2345',
        ],
    ],
    "ideal" => [
        'paymentMethodData' => [
            "id" => "pm_1234567893",
            "object" => "payment_method",
            "type" => "ideal",
            "ideal" => [
                "bank" => "ing",
                "bic" => "INGBNL2A"
            ]
        ],
        'expectedStrings' => [
            'iDEAL (ing)',
        ],
    ],
    "giropay" => [
        'paymentMethodData' => [
            "id" => "pm_1234567894",
            "object" => "payment_method",
            "type" => "giropay",
            "giropay" => []
        ],
        'expectedStrings' => [
            'Giropay',
        ],
    ],
    "bancontact" => [
        'paymentMethodData' => [
            "id" => "pm_1234567895",
            "object" => "payment_method",
            "type" => "bancontact",
            "bancontact" => []
        ],
        'expectedStrings' => [
            'Bancontact',
        ],
    ],
    "sofort" => [
        'paymentMethodData' => [
            "id" => "pm_1234567896",
            "object" => "payment_method",
            "type" => "sofort",
            "sofort" => [
                "country" => "DE"
            ]
        ],
        'expectedStrings' => [
            'SOFORT (DE)',
        ],
    ],
    "eps" => [
        'paymentMethodData' => [
            "id" => "pm_1234567897",
            "object" => "payment_method",
            "type" => "eps",
            "eps" => [
                "bank" => "bank_austria"
            ]
        ],
        'expectedStrings' => [
            'EPS (bank_austria)',
        ],
    ],
    "p24" => [
        'paymentMethodData' => [
            "id" => "pm_1234567898",
            "object" => "payment_method",
            "type" => "p24",
            "p24" => [
                "bank" => "ing"
            ]
        ],
        'expectedStrings' => [
            'P24',
        ],
    ],
    "fpx" => [
        'paymentMethodData' => [
            "id" => "pm_1234567899",
            "object" => "payment_method",
            "type" => "fpx",
            "fpx" => [
                "bank" => "maybank2u"
            ]
        ],
        'expectedStrings' => [
            'FPX (maybank2u)',
        ],
    ],
    "grabpay" => [
        'paymentMethodData' => [
            "id" => "pm_1234567900",
            "object" => "payment_method",
            "type" => "grabpay",
            "grabpay" => []
        ],
        'expectedStrings' => [
            'Grabpay',
        ],
    ],
    "alipay" => [
        'paymentMethodData' => [
            "id" => "pm_1234567901",
            "object" => "payment_method",
            "type" => "alipay",
            "alipay" => []
        ],
        'expectedStrings' => [
            'Alipay'
        ],
    ],
]);
