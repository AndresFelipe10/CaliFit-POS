<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDetailAddition extends Model
{
    use HasFactory;

    protected $table = 'sale_detail_additions';

    protected $fillable = [
        'sale_detail_id',
        'adicion_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function saleDetail(): BelongsTo
    {
        return $this->belongsTo(SaleDetail::class, 'sale_detail_id');
    }

    public function adicion(): BelongsTo
    {
        return $this->belongsTo(Addition::class, 'adicion_id');
    }

    public function addition(): BelongsTo
    {
        return $this->adicion();
    }
}
