<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $userId;
    public $tenantId;

    /**
     * Create a new event instance.
     */
    public function __construct(array $notification, $userId = null, $tenantId = null)
    {
        $this->notification = $notification;
        $this->userId = $userId;
        $this->tenantId = $tenantId ?: (tenant_context() ? tenant_context()->id : null);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Broadcast to tenant channel for general notifications
        if ($this->tenantId) {
            $channels[] = new PrivateChannel('notifications.' . $this->tenantId);
        }
        
        // Broadcast to specific user if specified
        if ($this->userId) {
            $channels[] = new PrivateChannel('user.' . $this->userId);
        }
        
        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notification.sent';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification,
            'timestamp' => now()->toISOString(),
        ];
    }
}