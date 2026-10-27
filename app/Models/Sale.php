<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $table = 'sales';

    protected $fillable = [
        'user_id',
        'customer_id',
        'customer_name',
        'customer_document',
        'waiter_name',
        'restaurant_order_id',
        'order_type',
        'invoice_number',
        'subtotal',
        'discount',
        'discount_percentage',
        'delivery_fee',
        'service_fee',
        'tax_inc',
        'tip',
        'total',
        'payment_method',
        'status',
        'notes',
        'is_credit',
        'credit_status',
        'credit_due_date',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'tax_inc' => 'decimal:2',
            'tip' => 'decimal:2',
            'total' => 'decimal:2',
            'is_credit' => 'boolean',
            'credit_due_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function restaurantOrder(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'restaurant_order_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class, 'sale_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'sale_id');
    }

    public function creditPayments(): HasMany
    {
        return $this->hasMany(CustomerCreditPayment::class, 'sale_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isCredit(): bool
    {
        return (bool) $this->is_credit;
    }

    public function getCreditPaidAmountAttribute(): float
    {
        if ($this->relationLoaded('creditPayments')) {
            return (float) $this->creditPayments->sum('amount');
        }

        return (float) $this->creditPayments()->sum('amount');
    }

    public function getCreditPendingAmountAttribute(): float
    {
        if (! $this->isCredit() || $this->isCancelled() || $this->credit_status === 'cancelled') {
            return 0.00;
        }

        $pending = round((float) $this->total - $this->credit_paid_amount, 2);

        return max(0.00, $pending);
    }

    /**
     * Accesor para fecha de venta con resolución garantizada a created_at.
     */
    public function getSaleDateAttribute($value): \Carbon\Carbon
    {
        if ($value) {
            return $value instanceof \Carbon\Carbon ? $value : \Carbon\Carbon::parse($value);
        }

        return $this->created_at instanceof \Carbon\Carbon 
            ? $this->created_at 
            : ($this->created_at ? \Carbon\Carbon::parse($this->created_at) : now());
    }
}
