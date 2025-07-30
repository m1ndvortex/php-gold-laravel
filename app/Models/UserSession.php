<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'device_type',
        'device_name',
        'browser',
        'platform',
        'location',
        'is_current',
        'last_activity',
        'logged_out_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'last_activity' => 'datetime',
        'logged_out_at' => 'datetime',
        'location' => 'array',
    ];

    /**
     * Get the user that owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if session is active.
     */
    public function isActive(): bool
    {
        return is_null($this->logged_out_at);
    }

    /**
     * Mark session as logged out.
     */
    public function logout(): void
    {
        $this->update([
            'logged_out_at' => now(),
            'is_current' => false,
        ]);
    }

    /**
     * Update session activity.
     */
    public function updateActivity(): void
    {
        $this->update([
            'last_activity' => now(),
        ]);
    }

    /**
     * Scope to get only active sessions.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('logged_out_at');
    }

    /**
     * Scope to get current session.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Get formatted device information.
     */
    public function getDeviceInfoAttribute(): string
    {
        $parts = array_filter([
            $this->device_name,
            $this->browser,
            $this->platform,
        ]);

        return implode(' - ', $parts) ?: 'Unknown Device';
    }

    /**
     * Get formatted location.
     */
    public function getLocationStringAttribute(): string
    {
        if (!$this->location) {
            return 'Unknown Location';
        }

        $parts = array_filter([
            $this->location['city'] ?? null,
            $this->location['country'] ?? null,
        ]);

        return implode(', ', $parts) ?: 'Unknown Location';
    }
}