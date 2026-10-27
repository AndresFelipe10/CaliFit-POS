<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addition extends Model
{
    use HasFactory;

    protected $table = 'adiciones';

    protected $fillable = [
        'nombre',
        'precio',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdditionItem::class, 'adicion_id');
    }

    public function detalles(): HasMany
    {
        return $this->items();
    }

    public function additionItems(): HasMany
    {
        return $this->items();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'producto_adicion', 'adicion_id', 'producto_id')
            ->withTimestamps();
    }

    public function platos(): BelongsToMany
    {
        return $this->products();
    }

    public function saleDetailAdditions(): HasMany
    {
        return $this->hasMany(SaleDetailAddition::class, 'adicion_id');
    }
}
