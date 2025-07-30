<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system_role',
        'is_active',
    ];

    protected $casts = [
        'is_system_role' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the users for the role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the permissions for the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('name', $permission)->exists();
    }

    /**
     * Assign permission to role.
     */
    public function givePermission(Permission $permission): void
    {
        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    /**
     * Assign multiple permissions to role.
     */
    public function givePermissions(array $permissions): void
    {
        $permissionIds = Permission::whereIn('name', $permissions)->pluck('id')->toArray();
        $this->permissions()->syncWithoutDetaching($permissionIds);
    }

    /**
     * Remove permission from role.
     */
    public function revokePermission(Permission $permission): void
    {
        $this->permissions()->detach($permission->id);
    }

    /**
     * Remove multiple permissions from role.
     */
    public function revokePermissions(array $permissions): void
    {
        $permissionIds = Permission::whereIn('name', $permissions)->pluck('id')->toArray();
        $this->permissions()->detach($permissionIds);
    }

    /**
     * Sync permissions for role.
     */
    public function syncPermissions(array $permissions): void
    {
        $permissionIds = Permission::whereIn('name', $permissions)->pluck('id')->toArray();
        $this->permissions()->sync($permissionIds);
    }

    /**
     * Check if role is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if role is a system role (cannot be deleted).
     */
    public function isSystemRole(): bool
    {
        return $this->is_system_role;
    }

    /**
     * Scope to get only active roles.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only non-system roles.
     */
    public function scopeNonSystem($query)
    {
        return $query->where('is_system_role', false);
    }
}