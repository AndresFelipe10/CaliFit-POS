<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'sku',
        'nombre',
        'descripcion',
        'tipo_producto',
        'unidad_base',
        'category_id',
        'stock',
        'stock_minimo',
        'precio_compra',
        'precio_venta',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'precio_compra' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function isDish(): bool
    {
        return $this->tipo_producto === 'PLATO';
    }

    public function isRawMaterial(): bool
    {
        return $this->tipo_producto === 'INSUMO';
    }

    public function isPreparation(): bool
    {
        return $this->tipo_producto === 'PREPARACION';
    }

    public function isSupplement(): bool
    {
        return $this->tipo_producto === 'SUPLEMENTO';
    }

    public function getFormattedStockAttribute(): string
    {
        return (float) $this->stock == (int) $this->stock
            ? (string) (int) $this->stock
            : rtrim(rtrim(number_format((float) $this->stock, 3, '.', ''), '0'), '.');
    }

    // Accessors y mutators de compatibilidad
    public function getNameAttribute(): string
    {
        return $this->nombre ?? '';
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['nombre'] = $value;
    }

    public function getSalePriceAttribute(): string
    {
        return (string) ($this->precio_venta ?? '0.00');
    }

    public function setSalePriceAttribute($value): void
    {
        $this->attributes['precio_venta'] = $value;
    }

    public function getCostPriceAttribute(): string
    {
        return (string) ($this->precio_compra ?? '0.00');
    }

    public function setCostPriceAttribute($value): void
    {
        $this->attributes['precio_compra'] = $value;
    }

    public function getIsActiveAttribute(): bool
    {
        return (bool) ($this->activo ?? true);
    }

    public function setIsActiveAttribute($value): void
    {
        $this->attributes['activo'] = (bool) $value;
    }

    public function getMinStockAttribute(): string
    {
        return (string) ($this->stock_minimo ?? '0.000');
    }

    public function setMinStockAttribute($value): void
    {
        $this->attributes['stock_minimo'] = $value;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function receta(): HasOne
    {
        return $this->hasOne(Recipe::class, 'producto_plato_id');
    }

    public function recipe(): HasOne
    {
        return $this->receta();
    }

    public function activeRecipe(): HasOne
    {
        return $this->hasOne(Recipe::class, 'producto_plato_id')->where('activa', true);
    }

    public function adiciones(): BelongsToMany
    {
        return $this->belongsToMany(Addition::class, 'producto_adicion', 'producto_id', 'adicion_id')
            ->withTimestamps();
    }

    public function additions(): BelongsToMany
    {
        return $this->adiciones();
    }

    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class, 'product_id');
    }

    public function restaurantOrderItems(): HasMany
    {
        return $this->hasMany(RestaurantOrderItem::class, 'product_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id');
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'ingrediente_id');
    }

    public function additionItems(): HasMany
    {
        return $this->hasMany(AdditionItem::class, 'ingrediente_id');
    }

    /**
     * Genera un SKU único, legible y secuencial para CaliFit.
     */
    public static function generateUniqueSku(string $tipoProducto = 'SUPLEMENTO'): string
    {
        $prefix = match ($tipoProducto) {
            'PLATO' => 'PLA-',
            'INSUMO' => 'INS-',
            'PREPARACION' => 'PREP-',
            default => 'SUP-',
        };

        $count = static::where('sku', 'like', "{$prefix}%")->count();
        $candidateNumber = $count + 1;

        do {
            $candidateSku = sprintf('%s%03d', $prefix, $candidateNumber);
            $exists = static::where('sku', $candidateSku)->exists();
            $candidateNumber++;
        } while ($exists);

        return $candidateSku;
    }
}
