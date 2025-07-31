<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'type',
        'title',
        'title_en',
        'message',
        'message_en',
        'scheduled_at',
        'sent_at',
        'channels',
        'metadata',
        'status',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'channels' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the customer that owns the notification
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope to get pending notifications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope to get failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get notifications by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get notifications scheduled for today or earlier
     */
    public function scopeDue($query)
    {
        return $query->where('scheduled_at', '<=', now());
    }

    /**
     * Check if notification is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if notification was sent
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Check if notification failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if notification was cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get localized title based on current locale
     */
    public function getLocalizedTitleAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'en' && $this->title_en ? $this->title_en : $this->title;
    }

    /**
     * Get localized message based on current locale
     */
    public function getLocalizedMessageAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'en' && $this->message_en ? $this->message_en : $this->message;
    }
}