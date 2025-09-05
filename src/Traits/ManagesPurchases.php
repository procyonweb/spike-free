<?php

namespace Opcodes\Spike\Traits;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Opcodes\Spike\Cart;
use Opcodes\Spike\CartItem;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\GroupedProductPurchase;
use Opcodes\Spike\Product;
use Opcodes\Spike\ProductPurchase;

trait ManagesPurchases
{
    public function purchases(): Collection
    {
        return Cache::driver('array')->remember('purchases::' . $this->spikeCacheIdentifier(), 10, function () {
            $cartItems = $this->purchasesQuery()
                ->selectRaw("product_id, quantity, paid_at")
                ->get();

            return $cartItems->map(function ($data) {
                return new ProductPurchase(
                    Spike::products()->firstWhere('id', $data->product_id),
                    $data->quantity,
                    Carbon::parse($data->paid_at)
                );
            });
        });
    }

    public function groupedPurchases(): Collection
    {
        return Cache::driver('array')->remember('grouped-purchases::' . $this->spikeCacheIdentifier(), 10, function () {
            $allProducts = Spike::products(includeArchived: true);

            $purchasedProductData = $this->purchasesQuery()
                ->selectRaw('product_id, sum(quantity) as quantity, min(paid_at) as first_purchase_at, max(paid_at) as last_purchase_at')
                ->groupBy('product_id')
                ->get();

            return $purchasedProductData->map(function ($data) use ($allProducts) {
                return new GroupedProductPurchase(
                    $allProducts->firstWhere('id', $data->product_id) ?? new Product($data->product_id, $data->product_id),
                    $data->quantity,
                    Carbon::parse($data->first_purchase_at),
                    Carbon::parse($data->last_purchase_at)
                );
            });
        });
    }

    public function hasPurchased(Product|string $product): bool
    {
        $productId = $product instanceof Product ? $product->id : (string) $product;

        return $this->groupedPurchases()
            ->contains(function (GroupedProductPurchase $purchase) use ($productId) {
                return $purchase->product->id === $productId
                    && $purchase->quantity > 0;
            });
    }

    protected function purchasesQuery(): Builder
    {
        $cartTable = (new Cart())->getTable();
        $cartItemsTable = (new CartItem())->getTable();

        return CartItem::query()
            ->leftJoin($cartTable, "$cartTable.id", '=', "$cartItemsTable.cart_id")
            ->where("$cartTable.billable_type", $this->getMorphClass())
            ->where("$cartTable.billable_id", $this->getKey())
            ->whereNotNull("$cartTable.paid_at")
            ->toBase();
    }
}
