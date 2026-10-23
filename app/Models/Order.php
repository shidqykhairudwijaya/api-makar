<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'buyer_id',
        'courier_id',
        'subtotal_products',
        'delivery_fee',
        'total_amount',
        'order_status',
        'payment_status',
        'delivery_address_snapshot',
        'notes',
        'ordered_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal_products' => 'integer',
            'delivery_fee' => 'integer',
            'total_amount' => 'integer',
            'order_status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'delivery_address_snapshot' => 'array',
            'ordered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Get the buyer who placed the order.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get the courier assigned to deliver the order.
     */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    /**
     * Get the line items for this order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the payment record associated with this order.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
