<?php

namespace Opcodes\Spike;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\PromotionCode;
use Opcodes\Spike\Actions\Products\ProvideCartProvidables;
use Opcodes\Spike\Contracts\CountableProvidable;
use Opcodes\Spike\Contracts\Providable;
use Opcodes\Spike\Contracts\SpikeBillable;
use Opcodes\Spike\Events\ProductPurchased;
use Opcodes\Spike\Facades\PaymentGateway;
use Opcodes\Spike\Stripe\CartCheckout;
use Opcodes\Spike\Facades\Spike;

/**
 * @property-read int $id
 * @property SpikeBillable $billable
 * @property-read CartItem[]|Collection $items
 * @property string $stripe_checkout_session_id
 * @property CarbonInterface $paid_at
 * @property CarbonInterface $deleted_at
 */
class Cart extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $table = 'spike_carts';

    protected $fillable = [
        'billable_type',
        'billable_id',
        'paid_at',
        'stripe_checkout_session_id',
        'promotion_code_id',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * @param SpikeBillable|Model $billable
     * @return static
     */
    public static function forBillable($billable): static
    {
        $cart = static::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', $billable->getKey())
            ->unpaid()
            ->latest('id')
            ->first();

        if (is_null($cart)) {
            $cart = new static;
            $cart->billable()->associate($billable);
            $cart->save();
        }

        return $cart;
    }

    /**
     * @return MorphTo|SpikeBillable
     */
    public function billable(): MorphTo
    {
        return $this->morphTo('billable');
    }

    /**
     * @return HasMany|Collection|CartItem[]
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }

    /**
     * @return HasMany|Collection|CreditTransaction[]
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'cart_id');
    }

    public function stripeCheckout(): CartCheckout
    {
        return new CartCheckout($this);
    }

    public function paddleCheckout(): \Opcodes\Spike\Paddle\CartCheckout
    {
        return new \Opcodes\Spike\Paddle\CartCheckout($this);
    }

    public function scopeWhereBillable($query, $billable)
    {
        $query->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', $billable->getKey());
    }

    public function scopeUnpaid($query)
    {
        $query->whereNull('paid_at');
    }

    public function scopePaid($query)
    {
        $query->whereNotNull('paid_at');
    }

    /**
     * Validate that all products in the cart have the same currency and return it
     *
     * @throws \InvalidArgumentException
     */
    public function validateAndDetermineCurrency(): ?string
    {
        $currencies = [];
        
        foreach ($this->items as $item) {
            $product = $item->product();
            if ($product && $product->currency) {
                $currencies[] = strtolower($product->currency);
            }
        }

        $uniqueCurrencies = array_unique($currencies);

        if (count($uniqueCurrencies) > 1) {
            throw new \InvalidArgumentException(
                'Cart contains products with different currencies: ' . implode(', ', $uniqueCurrencies) . 
                '. All products in a cart must have the same currency.'
            );
        }

        return $uniqueCurrencies[0] ?? null;
    }

    /**
     * Get the cart's currency based on its products
     */
    public function currency(): ?string
    {
        return $this->validateAndDetermineCurrency();
    }

    public function totalProvides(): \Illuminate\Support\Collection
    {
        $total = collect();

        foreach ($this->items as $cartItem) {
            foreach ($cartItem->totalProvides() as $provide) {
                /** @var Providable $provide */

                $existingTotal = $total->filter->isSameProvidable($provide)->first();

                if ($existingTotal instanceof CountableProvidable && $provide instanceof CountableProvidable) {
                    $existingTotal->setAmount($existingTotal->getAmount() + $provide->getAmount());
                } else {
                    foreach ($total as $item) {
                        if ($item->isSameProvidable($provide)) {
                            continue 2;
                        }
                    }

                    $total->push($provide);
                }
            }
        }

        return $total;
    }

    public function totalPriceInCents()
    {
        return $this->items->sum(fn (CartItem $item) => $item->totalPriceInCents());
    }

    public function promotionCode(): ?PromotionCode
    {
        if ($this->promotion_code_id && PaymentGateway::provider()->isStripe()) {
            $cacheKey = 'spike.cart_'.$this->id.'_promotion_code';
            $promotionCode = Cache::driver('array')->remember($cacheKey, 10, function () {
                return PaymentGateway::findStripePromotionCode($this->promotion_code_id);
            });

            if (is_null($promotionCode)) {
                // such promotion code doesn't exist anymore, let's unset it:
                $this->promotion_code_id = null;
                $this->save();

                Cache::driver('array')->forget($cacheKey);
            }

            return $promotionCode;
        }

        return null;
    }

    public function hasPromotionCode(): bool
    {
        $promotionCode = $this->promotionCode();

        return $promotionCode?->active ?? false;
    }

    public function totalPriceInCentsAfterDiscount(): int
    {
        $total = $this->totalPriceInCents();

        if ($this->hasPromotionCode() && ($coupon = $this->promotionCode()->coupon())) {
            if ($coupon->isPercentage()) {
                $total = $total * (1 - ($coupon->percentOff() / 100));
            } else {
                $total = max(0, $total - $coupon->rawAmountOff());
            }
        }

        return $total;
    }

    public function totalPriceAfterDiscountFormatted(): string
    {
        return Utils::formatAmount($this->totalPriceInCentsAfterDiscount(), $this->currency());
    }

    public function totalPriceFormatted(): string
    {
        return Utils::formatAmount($this->totalPriceInCents(), $this->currency());
    }

    public function empty(): bool
    {
        return $this->items()->doesntExist();
    }

    public function notEmpty(): bool
    {
        return ! $this->empty();
    }

    public function paid(): bool
    {
        return !is_null($this->paid_at);
    }

    public function hasProduct($id): bool
    {
        return $this->items->containsStrict('product_id', $id);
    }

    /**
     * Validate that a product can be added to this cart based on currency
     *
     * @throws \InvalidArgumentException
     */
    protected function validateProductCurrency($productId): void
    {
        $product = Spike::findProduct($productId, $this->billable);

        if (!$product) {
            return; // Product doesn't exist, let it fail elsewhere
        }

        // If cart is empty, any currency is allowed
        if ($this->items()->doesntExist()) {
            return;
        }

        $cartCurrency = $this->currency();
        $productCurrency = $product->currency ? strtolower($product->currency) : null;

        // If both are null, that's fine
        if (is_null($cartCurrency) && is_null($productCurrency)) {
            return;
        }

        // If one is set and the other isn't, that might be okay for backward compatibility
        // But if both are set, they must match
        if ($cartCurrency && $productCurrency && $cartCurrency !== $productCurrency) {
            throw new \InvalidArgumentException(
                "Cannot add product with currency '{$product->currency}' to cart with currency '{$cartCurrency}'. " .
                "All products in a cart must have the same currency."
            );
        }
    }

    public function addProduct($id, $quantity = 1)
    {
        if ($this->paid()) return;

        $this->validateProductCurrency($id);

        if (!$this->items()->where('product_id', $id)->exists()) {
            $this->items()->create(['product_id' => $id, 'quantity' => 0]);
        }

        $this->items()
            ->where('product_id', $id)
            ->increment('quantity', $quantity);

        $this->stripeCheckout()->resetCheckout();
    }

    public function removeProduct($id, $quantity = 1)
    {
        if ($this->paid()) return;

        $this->items()
            ->where('product_id', $id)
            ->decrement('quantity', $quantity);

        $this->items()
            ->where('product_id', $id)
            ->where('quantity', '<=', 0)
            ->delete();

        $this->stripeCheckout()->resetCheckout();
    }

    public function removeProductCompletely($id)
    {
        if ($this->paid()) return;

        $this->items()->where('product_id', $id)->delete();

        $this->stripeCheckout()->resetCheckout();
    }

    public function markAsSuccessfullyPaid(): void
    {
        if ($this->paid()) {
            return;
        }

        $this->update(['paid_at' => now()]);

        app(ProvideCartProvidables::class)->handle($this);

        $this->items->each(function (CartItem $item) {
            event(new ProductPurchased(
                $this->billable,
                $item->product(),
                $item->quantity
            ));
        });
    }

    public function syncWithStripeCheckoutSession(): void
    {
        if ($this->paid()) return;
        if (! PaymentGateway::provider()->isStripe()) return;

        $checkoutSession = $this->stripeCheckout()->toStripeCheckoutSession();

        if (!$checkoutSession || $checkoutSession->payment_status === \Stripe\Checkout\Session::PAYMENT_STATUS_UNPAID) {
            return;
        }

        $this->markAsSuccessfullyPaid();
    }
}
