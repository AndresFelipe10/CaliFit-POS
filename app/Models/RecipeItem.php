<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends Model
{
    use HasFactory;

    protected $table = 'receta_detalle';

    protected $fillable = [
        'receta_id',
        'ingrediente_id',
        'cantidad_por_porcion',
        'unidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_por_porcion' => 'decimal:3',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'receta_id');
    }

    public function receta(): BelongsTo
    {
        return $this->recipe();
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingrediente_id');
    }

    public function ingrediente(): BelongsTo
    {
        return $this->ingredient();
    }
}
