<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';

    protected $fillable = [
        'name',
        'document',
        'email',
        'phone',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getNombreAttribute(): string
    {
        return $this->name ?? '';
    }

    public function setNombreAttribute($value): void
    {
        $this->attributes['name'] = $value;
    }

    public function getTelefonoAttribute(): ?string
    {
        return $this->phone;
    }

    public function setTelefonoAttribute($value): void
    {
        $this->attributes['phone'] = $value;
    }

    public function getDireccionAttribute(): ?string
    {
        return $this->address;
    }

    public function setDireccionAttribute($value): void
    {
        $this->attributes['address'] = $value;
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function creditSales(): HasMany
    {
        return $this->hasMany(Sale::class)->where('is_credit', true);
    }

    public function creditPayments(): HasMany
    {
        return $this->hasMany(CustomerCreditPayment::class);
    }

    public function restaurantOrders(): HasMany
    {
        return $this->hasMany(RestaurantOrder::class);
    }

    public function getCreditBalanceAttribute(): float
    {
        $totalCredit = (float) $this->creditSales()
            ->where('status', 'completed')
            ->sum('total');

        $totalPaid = (float) $this->creditPayments()
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed'))
            ->sum('amount');

        return round(max(0.0, $totalCredit - $totalPaid), 2);
    }

    public function getPendingCreditSalesCountAttribute(): int
    {
        return $this->creditSales()
            ->where('status', 'completed')
            ->where('credit_status', 'pending')
            ->count();
    }
}

