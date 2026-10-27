<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdditionItem extends Model
{
    use HasFactory;

    protected $table = 'adicion_detalle';

    protected $fillable = [
        'adicion_id',
        'ingrediente_id',
        'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
        ];
    }

    public function addition(): BelongsTo
    {
        return $this->belongsTo(Addition::class, 'adicion_id');
    }

    public function adicion(): BelongsTo
    {
        return $this->addition();
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
