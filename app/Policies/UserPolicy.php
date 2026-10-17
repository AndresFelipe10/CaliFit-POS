<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Tenant\TenantManager;

class UserPolicy
{
    /**
     * Determine whether the user can view any team members in the current business.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCurrentAdmin();
    }

    /**
     * Determine whether the user can invite/add team members to the current business.
     * El aprovisionamiento de credenciales de usuario es exclusivo del Superadministrador de la plataforma.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the role of a team member in the current business.
     */
    public function update(User $user, User $model): bool
    {
        if (! $user->isCurrentAdmin()) {
            return false;
        }

        $businessId = app(TenantManager::class)->get()?->id ?: session('current_business_id');

        return (bool) $model->businesses()
            ->where('businesses.id', $businessId)
            ->exists();
    }

    /**
     * Determine whether the user can deactivate a team member from the current business.
     */
    public function deactivate(User $user, User $model): bool
    {
        if (! $user->isCurrentAdmin() || $user->id === $model->id) {
            return false;
        }

        $businessId = app(TenantManager::class)->get()?->id ?: session('current_business_id');

        return (bool) $model->businesses()
            ->where('businesses.id', $businessId)
            ->exists();
    }
}
