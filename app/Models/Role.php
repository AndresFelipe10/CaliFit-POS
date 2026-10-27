<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_EMPLOYEE = 'employee';

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function isAdmin(): bool
    {
        return $this->slug === self::ROLE_ADMIN;
    }

    public function isEmployee(): bool
    {
        return $this->slug === self::ROLE_EMPLOYEE;
    }
}
