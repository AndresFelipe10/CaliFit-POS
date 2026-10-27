<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Determine whether the user is an admin.
     */
    protected function canManage(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view any expenses.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can view the expense.
     */
    public function view(User $user, Expense $expense): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can create expenses.
     */
    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can update the expense.
     */
    public function update(User $user, Expense $expense): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can delete the expense.
     */
    public function delete(User $user, Expense $expense): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can mark the expense as paid.
     */
    public function pay(User $user, Expense $expense): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can download or view the expense attachment.
     */
    public function downloadAttachment(User $user, Expense $expense): bool
    {
        return $this->canManage($user);
    }
}
