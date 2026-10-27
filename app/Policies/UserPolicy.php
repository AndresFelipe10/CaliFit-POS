<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any team members in CaliFit POS.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create team members in CaliFit POS.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update a team member in CaliFit POS.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can deactivate a team member.
     */
    public function deactivate(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }
}
