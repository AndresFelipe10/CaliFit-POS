<?php

namespace App\Policies;

use App\Models\RestaurantTable;
use App\Models\User;

class RestaurantTablePolicy
{
    /**
     * Determine whether the user can view any tables.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    /**
     * Determine whether the user can view the table.
     */
    public function view(User $user, RestaurantTable $table): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    /**
     * Determine whether the user can create tables.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the table.
     */
    public function update(User $user, RestaurantTable $table): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the table.
     */
    public function delete(User $user, RestaurantTable $table): bool
    {
        return $user->isAdmin();
    }
}
