@if(\Opcodes\Spike\Facades\Spike::paymentProvider()->isPaddle())
    <?php
    $seller = array_filter([
        'seller' => (int) config('cashier.seller_id'),
        'pwAuth' => (int) config('cashier.retain_key'),
        'allowLogout' => false,
    ]);

    if (isset($seller['pwAuth']) && \Illuminate\Support\Facades\Auth::check() && $customer = \Illuminate\Support\Facades\Auth::user()->customer) {
        $seller['pwCustomer'] = ['id' => $customer->paddle_id];
    }
    ?>

    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>

    @if (config('cashier.sandbox'))
        <script type="text/javascript">
            Paddle.Environment.set('sandbox');
        </script>
    @endif

    <script type="text/javascript">
        Paddle.Setup(Object.assign(@json($seller), {
            eventCallback: function (event) {
                if (event.name === 'checkout.closed') {
                    window.Livewire.dispatch('checkoutClosed');
                    // just in case the user closed the checkout too fast before webhook was handled.
                    setTimeout(function () {
                        window.Livewire.dispatch('checkoutClosed');
                    }, 4000);
                }
            }
        }));
    </script>
@endif
