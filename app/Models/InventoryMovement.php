<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    use HasFactory;

    public const TYPE_SALIDA_VENTA = 'SALIDA_VENTA';
    public const TYPE_ENTRADA_COMPRA = 'ENTRADA_COMPRA';
    public const TYPE_MERMA = 'MERMA';
    public const TYPE_AJUSTE = 'AJUSTE';
    public const TYPE_DEVOLUCION_ANULACION = 'DEVOLUCION_ANULACION';

    protected $table = 'inventory_movements';

    protected $fillable = [
        'product_id',
        'user_id',
        'type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reason',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'previous_stock' => 'decimal:3',
            'new_stock' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
