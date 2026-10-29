<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantOrder extends Model
{
    use HasFactory;

    protected $table = 'restaurant_orders';

    protected $fillable = [
        'table_id',
        'user_id',
        'order_number',
        'order_type',
        'status',
        'guest_count',
        'customer_id',
        'customer_name',
        'delivery_phone',
        'delivery_address',
        'waiter_name',
        'delivery_fee',
        'subtotal',
        'tip',
        'total',
        'notes',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'closed_at',
        'split_type',
        'total_shares',
        'paid_shares',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'delivery_fee' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tip' => 'decimal:2',
            'total' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'closed_at' => 'datetime',
            'total_shares' => 'integer',
            'paid_shares' => 'integer',
        ];
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class, 'restaurant_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RestaurantOrderItem::class, 'order_id');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(RestaurantOrderSplit::class, 'restaurant_order_id');
    }

    public function pendingKitchenItems(): HasMany
    {
        return $this->hasMany(RestaurantOrderItem::class, 'order_id')
            ->where('printed_to_kitchen', false)
            ->where('status', '!=', 'cancelled');
    }

    public function recalculateTotals(): void
    {
        if ($this->order_type === 'takeout') {
            $this->delivery_fee = 0.00;
        }

        $activeItems = $this->items()
            ->where('status', '!=', 'cancelled')
            ->where('is_paid', false)
            ->get();

        $subtotal = 0.0;

        foreach ($activeItems as $item) {
            $subtotal += round((float) $item->subtotal, 2);
        }

        $this->subtotal = round($subtotal, 2);
        $this->total = round($this->subtotal + (float) $this->delivery_fee, 2);
        $this->save();
    }

    public function isSplit(): bool
    {
        return in_array($this->split_type, ['equal', 'items'], true);
    }

    public function getPendingAmountAttribute(): float
    {
        return (float) $this->total;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'in_kitchen', 'dispatched', 'delivered']);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'En Cocina',
            'in_kitchen' => 'En Cocina',
            'dispatched' => 'En Camino / Despachada',
            'delivered' => 'Entregada',
            'billed' => 'En Cobro / Pre-cuenta emitida',
            'closed' => 'Cerrada / Pagada',
            'cancelled' => 'Anulada',
            default => ucfirst($this->status),
        };
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

    /**
     * Retorna el teléfono de contacto priorizando columna directa, cliente o parsing histórico.
     */
    public function getPhoneAttribute(): ?string
    {
        if (!empty($this->delivery_phone)) {
            return $this->delivery_phone;
        }

        if ($this->customer && !empty($this->customer->phone)) {
            return $this->customer->phone;
        }

        return $this->parsePhoneFromNotes();
    }

    /**
     * Retorna la dirección de entrega priorizando columna directa, cliente o parsing histórico.
     */
    public function getAddressAttribute(): ?string
    {
        if (!empty($this->delivery_address)) {
            return $this->delivery_address;
        }

        if ($this->customer && !empty($this->customer->address)) {
            return $this->customer->address;
        }

        return $this->parseAddressFromNotes();
    }

    /**
     * Retorna las observaciones reales digitadas por el usuario, depurando prefijos históricos Tel/Dir.
     */
    public function getCleanNotesAttribute(): ?string
    {
        $raw = trim((string) $this->notes);
        if ($raw === '') {
            return null;
        }

        // Si no tiene prefijos de concatenación legacy, devolver tal cual
        if (!str_contains($raw, 'Tel:') && !str_contains($raw, 'Dir:')) {
            return $raw;
        }

        // Separar partes divididas por '|'
        $parts = array_map('trim', explode('|', $raw));
        $cleanParts = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, 'Tel:') || str_starts_with($part, 'Dir:')) {
                continue;
            }
            if ($part !== '') {
                $cleanParts[] = $part;
            }
        }

        return !empty($cleanParts) ? implode(' | ', $cleanParts) : null;
    }

    protected function parsePhoneFromNotes(): ?string
    {
        $raw = (string) $this->notes;
        if (preg_match('/Tel:\s*([^|]+)/i', $raw, $matches)) {
            $val = trim($matches[1]);
            return $val !== '' ? $val : null;
        }
        return null;
    }

    protected function parseAddressFromNotes(): ?string
    {
        $raw = (string) $this->notes;
        if (preg_match('/Dir:\s*([^|]+)/i', $raw, $matches)) {
            $val = trim($matches[1]);
            return $val !== '' ? $val : null;
        }
        return null;
    }
}
