<?php
$options = array_merge($checkout->options(), [
    'settings' => [
        'displayMode' => 'overlay',
        'theme' => 'light',
        'locale' => \App::getLocale(),
        'allowLogout' => false,
        'successUrl' => $checkout->getReturnUrl(),
    ],
]);
?>

<button
    x-on:click='Paddle.Checkout.open(@json($options))'
    {{ $attributes->except(['checkout']) }}
>
    {{ $slot }}
</button>
