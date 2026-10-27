<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantOrderItemAddition extends Model
{
    use HasFactory;

    protected $table = 'restaurant_order_item_additions';

    protected $fillable = [
        'order_item_id',
        'adicion_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrderItem::class, 'order_item_id');
    }

    public function addition(): BelongsTo
    {
        return $this->belongsTo(Addition::class, 'adicion_id');
    }

    public function adicion(): BelongsTo
    {
        return $this->addition();
    }
}
