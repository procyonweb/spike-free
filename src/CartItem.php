<?php

namespace Opcodes\Spike;

use Opcodes\Spike\Contracts\CountableProvidable;
use Opcodes\Spike\Contracts\Providable;
use Opcodes\Spike\Contracts\ProvideHistoryRelatableItemContract;
use Opcodes\Spike\Facades\Spike;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property int $cart_id
 * @property string $product_id
 */
class CartItem extends Model implements ProvideHistoryRelatableItemContract
{
    use HasFactory;

    protected $table = 'spike_cart_items';

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
    ];

    /**
     * @return BelongsTo|Cart
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return HasMany|Collection|CreditTransaction[]
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'cart_item_id');
    }

    public function product(): ?Product
    {
        return Spike::findProduct($this->product_id, $this->cart->billable);
    }

    public function totalProvides(): \Illuminate\Support\Collection
    {
        $total = collect();
        $product = $this->product();

        if ($product && $product->provides) {
            foreach ($product->provides as $provide) {
                /** @var Providable $provide */
                if ($provide instanceof CountableProvidable) {
                    $newProvide = clone $provide;
                    $newProvide->setAmount($provide->getAmount() * $this->quantity);
                    $total->push($newProvide);
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

    public function totalPriceInCents(): int
    {
        $total = 0;
        $product = $this->product();

        if ($product && $product->price_in_cents) {
            $total = $product->price_in_cents * $this->quantity;
        }

        return $total;
    }

    public function totalPriceFormatted(): string
    {
        $currency = $this->cart->currency();
        return Utils::formatAmount($this->totalPriceInCents(), $currency);
    }

    public function provideHistoryId(): string
    {
        return $this->getKey();
    }

    public function provideHistoryType(): string
    {
        return $this->getMorphClass();
    }
}
