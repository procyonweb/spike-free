<?php

namespace Opcodes\Spike\Mollie;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'mollie_orders';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
        'amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'billable_id',
        'billable_type',
        'mollie_order_id',
        'mollie_payment_id',
        'mollie_payment_status',
        'number',
        'currency',
        'amount',
        'metadata',
    ];

    /**
     * Get the billable model related to this order.
     */
    public function billable()
    {
        return $this->morphTo();
    }

    /**
     * Get the total amount for display.
     */
    public function getTotal(): string
    {
        return $this->currency . ' ' . number_format($this->amount / 100, 2);
    }
}
