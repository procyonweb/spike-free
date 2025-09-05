<?php

namespace Opcodes\Spike\Actions\Products;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\Contracts\Providable;
use Opcodes\Spike\ProvideHistory;

class ProvideCartProvidables
{
    public function handle(Cart $cart): void
    {
        $cart->items->each(function (CartItem $item) use ($cart) {
            foreach ($item->totalProvides() as $provide) {
                /** @var Providable $provide */

                if (ProvideHistory::hasProvided($item, $provide, $cart->billable)) {
                    continue;
                }

                DB::beginTransaction();

                try {
                    $provide->provideOnceFromProduct($item->product(), $cart->billable);

                    ProvideHistory::createSuccessfulProvide(
                        $item, $provide, $cart->billable
                    );

                    DB::commit();

                } catch (\Exception $exception) {

                    DB::rollBack();

                    ProvideHistory::createFailedProvide(
                        $item, $provide, $cart->billable, $exception
                    );

                    Log::error($exception);
                }
            }
        });
    }
}
