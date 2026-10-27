<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCreditPayment extends Model
{
    use HasFactory;

    protected $table = 'customer_credit_payments';

    protected $fillable = [
        'sale_id',
        'customer_id',
        'user_id',
        'method',
        'amount',
        'cash_received',
        'change_given',
        'reference',
        'notes',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_given' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta débito/crédito',
            'transfer' => 'Transferencia / Nequi / Daviplata',
            'other' => 'Otro Medio',
            default => ucfirst((string) $this->method),
        };
    }
}
