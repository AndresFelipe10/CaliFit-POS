<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'nit',
        'contact_name',
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

    public function getIdentificationNumberAttribute(): ?string
    {
        return $this->nit;
    }

    public function setIdentificationNumberAttribute($value): void
    {
        $this->attributes['nit'] = $value;
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
