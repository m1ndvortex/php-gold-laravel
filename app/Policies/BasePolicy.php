<?php

namespace App\Policies;

use App\Models\User;

abstract class BasePolicy
{
    /**
     * Check if user has permission.
     */
    protected function hasPermission(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }

    /**
     * Check if user has any of the given permissions.
     */
    protected function hasAnyPermission(User $user, array $permissions): bool
    {
        return $user->hasAnyPermission($permissions);
    }

    /**
     * Check if user has all of the given permissions.
     */
    protected function hasAllPermissions(User $user, array $permissions): bool
    {
        return $user->hasAllPermissions($permissions);
    }

    /**
     * Check if user has specific role.
     */
    protected function hasRole(User $user, string $role): bool
    {
        return $user->role && $user->role->name === $role;
    }

    /**
     * Check if user has any of the given roles.
     */
    protected function hasAnyRole(User $user, array $roles): bool
    {
        return $user->role && in_array($user->role->name, $roles);
    }

    /**
     * Check if user is active.
     */
    protected function isActive(User $user): bool
    {
        return $user->isActive();
    }
}