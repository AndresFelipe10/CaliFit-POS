<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantOrderItem extends Model
{
    use HasFactory;

    protected $table = 'restaurant_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
        'notes',
        'status',
        'printed_to_kitchen',
        'batch_number',
        'is_paid',
        'paid_quantity',
        'sale_id',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'paid_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'printed_to_kitchen' => 'boolean',
            'batch_number' => 'integer',
            'is_paid' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getPendingQuantityAttribute(): float
    {
        if ($this->is_paid || $this->status === 'cancelled') {
            return 0.0;
        }

        return max(0.0, round((float) $this->quantity - (float) ($this->paid_quantity ?? 0), 3));
    }

    public function getPendingSubtotalAttribute(): float
    {
        $pendingQty = $this->pending_quantity;
        if ($pendingQty <= 0.0001) {
            return 0.0;
        }

        if ((float) ($this->paid_quantity ?? 0) <= 0.0001) {
            return round((float) $this->subtotal, 2);
        }

        $baseSubtotal = round($pendingQty * (float) $this->unit_price, 2);
        $additionsSubtotal = 0.0;

        foreach ($this->additions as $add) {
            $pendingAddQty = $add->pending_quantity;
            if ($pendingAddQty > 0) {
                $additionsSubtotal += round($pendingAddQty * (float) $add->unit_price, 2);
            }
        }

        return round($baseSubtotal + $additionsSubtotal, 2);
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function additions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RestaurantOrderItemAddition::class, 'order_item_id');
    }
}
