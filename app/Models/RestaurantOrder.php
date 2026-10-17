<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantOrder extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'restaurant_orders';

    protected $fillable = [
        'business_id',
        'table_id',
        'guest_count',
        'user_id',
        'sale_id',
        'order_number',
        'order_type',
        'status',
        'customer_id',
        'customer_name',
        'delivery_phone',
        'delivery_address',
        'delivery_notes',
        'delivery_fee',
        'subtotal',
        'total',
        'notes',
        'closed_at',
        'precount_printed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $appends = [
        'pending_balance',
        'paid_amount',
        'total_amount',
        'has_partial_payments',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'delivery_fee' => 'float',
            'subtotal' => 'float',
            'total' => 'float',
            'closed_at' => 'datetime',
            'precount_printed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RestaurantOrderItem::class, 'order_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'restaurant_order_id');
    }

    public function pendingKitchenItems(): HasMany
    {
        return $this->hasMany(RestaurantOrderItem::class, 'order_id')
            ->where('printed_to_kitchen', false)
            ->where('status', '!=', 'cancelled');
    }

    /**
     * Colección de ítems con payment_status === 'pending'.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, RestaurantOrderItem>
     */
    public function unpaidItems(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->relationLoaded('items')) {
            return $this->items
                ->where('payment_status', 'pending')
                ->where('status', '!=', 'cancelled')
                ->values();
        }

        return $this->items()
            ->where('payment_status', 'pending')
            ->where('status', '!=', 'cancelled')
            ->get();
    }

    public function getUnpaidItemsAttribute(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->unpaidItems();
    }

    /**
     * Suma del subtotal de todos los ítems activos (no cancelados) de la comanda (+ flete de domicilio si aplica).
     */
    public function totalAmount(): float
    {
        $sum = $this->relationLoaded('items')
            ? (float) $this->items->where('status', '!=', 'cancelled')->sum('subtotal')
            : (float) $this->items()->where('status', '!=', 'cancelled')->sum('subtotal');

        $deliveryFee = (float) ($this->delivery_fee ?? 0);

        return round($sum + $deliveryFee, 2);
    }

    public function getTotalAmountAttribute(): float
    {
        return $this->totalAmount();
    }

    /**
     * Suma del subtotal de los ítems con payment_status === 'paid' (no cancelados).
     */
    public function paidAmount(): float
    {
        $sum = $this->relationLoaded('items')
            ? (float) $this->items->where('payment_status', 'paid')->where('status', '!=', 'cancelled')->sum('subtotal')
            : (float) $this->items()->where('payment_status', 'paid')->where('status', '!=', 'cancelled')->sum('subtotal');

        return round($sum, 2);
    }

    public function getPaidAmountAttribute(): float
    {
        return $this->paidAmount();
    }

    /**
     * Suma del subtotal de los ítems con payment_status === 'pending' (+ flete de domicilio si aún no se ha liquidado).
     */
    public function pendingBalance(): float
    {
        $sum = $this->relationLoaded('items')
            ? (float) $this->items->where('payment_status', 'pending')->where('status', '!=', 'cancelled')->sum('subtotal')
            : (float) $this->items()->where('payment_status', 'pending')->where('status', '!=', 'cancelled')->sum('subtotal');

        $fee = (float) ($this->delivery_fee ?? 0);

        return round(max(0, $sum + $fee), 2);
    }

    public function getPendingBalanceAttribute(): float
    {
        return $this->pendingBalance();
    }

    /**
     * Retorna true si la comanda tiene pagos parciales y aún tiene saldo pendiente.
     */
    public function hasPartialPayments(): bool
    {
        return $this->paidAmount() > 0 && $this->pendingBalance() > 0;
    }

    public function getHasPartialPaymentsAttribute(): bool
    {
        return $this->hasPartialPayments();
    }

    /**
     * Retorna true si no quedan ítems pendientes.
     */
    public function isFullyPaid(): bool
    {
        $pendingCount = $this->relationLoaded('items')
            ? $this->items->where('payment_status', 'pending')->where('status', '!=', 'cancelled')->count()
            : $this->items()->where('payment_status', 'pending')->where('status', '!=', 'cancelled')->count();

        return $pendingCount === 0;
    }

    public function recalculateTotals(): void
    {
        $activeItems = $this->items()->where('status', '!=', 'cancelled')->get();
        $subtotal = 0.0;

        foreach ($activeItems as $item) {
            $subtotal += round((float) $item->quantity * (float) $item->unit_price, 2);
        }

        $this->subtotal = round($subtotal, 2);
        $this->total = round($this->subtotal + (float) $this->delivery_fee, 2);
        $this->save();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'in_kitchen', 'dispatched', 'delivered']);
    }

    public function isClosedOrBilled(): bool
    {
        return in_array($this->status, ['billed', 'closed', 'paid'], true);
    }

    public function isClosed(): bool
    {
        return $this->isClosedOrBilled();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeModified(): bool
    {
        return ! $this->isClosedOrBilled() && ! $this->isCancelled();
    }

    /**
     * Suma de propinas registradas en todas las ventas asociadas a la comanda.
     */
    public function totalTips(): float
    {
        if ($this->relationLoaded('sales')) {
            return (float) round($this->sales->sum(fn ($s) => (float) ($s->service_fee ?? 0)), 2);
        }

        return (float) round((float) $this->sales()->sum('service_fee'), 2);
    }

    public function getTotalTipsAttribute(): float
    {
        return $this->totalTips();
    }

    /**
     * Suma de impuestos registrados en todas las ventas asociadas a la comanda.
     */
    public function totalTaxes(): float
    {
        if ($this->relationLoaded('sales')) {
            return (float) round($this->sales->sum(fn ($s) => (float) ($s->tax_inc ?? 0)), 2);
        }

        return (float) round((float) $this->sales()->sum('tax_inc'), 2);
    }

    public function getTotalTaxesAttribute(): float
    {
        return $this->totalTaxes();
    }

    /**
     * Total recaudado = Consumo neto + Propinas + Impuestos (suma del total de todas las ventas asociadas).
     */
    public function totalCollected(): float
    {
        if ($this->relationLoaded('sales')) {
            return (float) round($this->sales->sum(fn ($s) => (float) ($s->total ?? 0)), 2);
        }

        return (float) round((float) $this->sales()->sum('total'), 2);
    }

    public function getTotalCollectedAttribute(): float
    {
        return $this->totalCollected();
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->isCancelled()) {
            return 'Anulada';
        }

        if ($this->isClosedOrBilled()) {
            return 'Cerrada / Pagada';
        }

        if (in_array($this->status, ['open', 'in_kitchen', 'precount', 'in_payment'], true) && (! empty($this->precount_printed_at) || $this->hasPartialPayments() || in_array($this->status, ['precount', 'in_payment'], true))) {
            return 'En Cobro / Pre-cuenta emitida';
        }

        return match ($this->status) {
            'in_kitchen' => 'En Cocina',
            'dispatched' => 'En Camino / Despachada',
            'delivered' => 'Entregada',
            default => 'Abierta / En preparación',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        if ($this->isCancelled()) {
            return 'bg-danger text-white';
        }

        if ($this->isClosedOrBilled()) {
            return 'bg-success text-white';
        }

        if (in_array($this->status, ['open', 'in_kitchen', 'precount', 'in_payment'], true) && (! empty($this->precount_printed_at) || $this->hasPartialPayments() || in_array($this->status, ['precount', 'in_payment'], true))) {
            return 'bg-warning text-dark';
        }

        if ($this->status === 'in_kitchen') {
            return 'bg-warning text-dark';
        }

        if ($this->status === 'dispatched') {
            return 'bg-info text-dark';
        }

        return 'bg-primary text-white';
    }

    public function getStatusBadgeAttribute(): string
    {
        $label = $this->status_label;
        $class = $this->status_badge_class;

        return "<span class=\"badge {$class} px-2 py-1\">{$label}</span>";
    }

    public function getOrderTypeLabelAttribute(): string
    {
        return match ($this->order_type) {
            'table' => 'Mesa / Salón',
            'delivery' => 'Domicilio',
            'takeout' => 'Para Llevar / Recoger',
            default => ucfirst($this->order_type),
        };
    }
}
