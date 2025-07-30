<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'users.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile or have permission
        return $user->id === $model->id || $this->hasPermission($user, 'users.view');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'users.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile or have permission
        return $user->id === $model->id || $this->hasPermission($user, 'users.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        return $this->hasPermission($user, 'users.delete');
    }

    /**
     * Determine whether the user can manage roles.
     */
    public function manageRoles(User $user): bool
    {
        return $this->hasPermission($user, 'users.manage_roles');
    }

    /**
     * Determine whether the user can manage permissions.
     */
    public function managePermissions(User $user): bool
    {
        return $this->hasPermission($user, 'users.manage_permissions');
    }

    /**
     * Determine whether the user can activate/deactivate users.
     */
    public function toggleStatus(User $user, User $model): bool
    {
        // Users cannot deactivate themselves
        if ($user->id === $model->id) {
            return false;
        }

        return $this->hasPermission($user, 'users.toggle_status');
    }
}