<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $productId;
    public $productName;
    public $oldStock;
    public $newStock;
    public $changeType;
    public $tenantId;

    /**
     * Create a new event instance.
     */
    public function __construct($productId, $productName, $oldStock, $newStock, $changeType, $tenantId = null)
    {
        $this->productId = $productId;
        $this->productName = $productName;
        $this->oldStock = $oldStock;
        $this->newStock = $newStock;
        $this->changeType = $changeType; // 'sale', 'purchase', 'adjustment', 'return'
        $this->tenantId = $tenantId ?: (tenant_context() ? tenant_context()->id : null);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('inventory.' . $this->tenantId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'inventory.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'old_stock' => $this->oldStock,
            'new_stock' => $this->newStock,
            'change_type' => $this->changeType,
            'change_amount' => $this->newStock - $this->oldStock,
            'timestamp' => now()->toISOString(),
        ];
    }
}