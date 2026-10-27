<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasFactory;

    protected $table = 'recetas';

    protected $fillable = [
        'producto_plato_id',
        'nombre',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_plato_id');
    }

    public function plato(): BelongsTo
    {
        return $this->product();
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'receta_id');
    }

    public function detalles(): HasMany
    {
        return $this->items();
    }

    public function recipeItems(): HasMany
    {
        return $this->items();
    }
}
