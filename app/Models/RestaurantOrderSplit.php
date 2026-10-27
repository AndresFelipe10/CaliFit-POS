<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantOrderSplit extends Model
{
    use HasFactory;

    protected $table = 'restaurant_order_splits';

    protected $fillable = [
        'restaurant_order_id',
        'share_number',
        'total_shares',
        'amount',
        'status',
        'payment_method',
        'cash_received',
        'change_given',
        'reference',
        'user_id',
        'sale_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'share_number' => 'integer',
            'total_shares' => 'integer',
            'amount' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_given' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'restaurant_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
