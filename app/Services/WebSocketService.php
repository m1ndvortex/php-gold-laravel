<?php

namespace App\Services;

use App\Events\DashboardUpdated;
use App\Events\InventoryUpdated;
use App\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

class WebSocketService
{
    /**
     * Broadcast dashboard updates to all connected users in the tenant
     */
    public function broadcastDashboardUpdate(array $data, $tenantId = null): void
    {
        try {
            event(new DashboardUpdated($data, $tenantId));
            
            Log::info('Dashboard update broadcasted', [
                'tenant_id' => $tenantId,
                'data_keys' => array_keys($data)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast dashboard update', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
        }
    }

    /**
     * Broadcast inventory level changes to all connected users in the tenant
     */
    public function broadcastInventoryUpdate($productId, $productName, $oldStock, $newStock, $changeType, $tenantId = null): void
    {
        try {
            event(new InventoryUpdated($productId, $productName, $oldStock, $newStock, $changeType, $tenantId));
            
            Log::info('Inventory update broadcasted', [
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'change_type' => $changeType,
                'stock_change' => $newStock - $oldStock
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast inventory update', [
                'error' => $e->getMessage(),
                'product_id' => $productId,
                'tenant_id' => $tenantId
            ]);
        }
    }

    /**
     * Broadcast notifications to users
     */
    public function broadcastNotification(array $notification, $userId = null, $tenantId = null): void
    {
        try {
            event(new NotificationSent($notification, $userId, $tenantId));
            
            Log::info('Notification broadcasted', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'notification_type' => $notification['type'] ?? 'unknown'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast notification', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);
        }
    }

    /**
     * Broadcast KPI updates to dashboard
     */
    public function broadcastKpiUpdate(array $kpis, $tenantId = null): void
    {
        $this->broadcastDashboardUpdate([
            'type' => 'kpi_update',
            'kpis' => $kpis
        ], $tenantId);
    }

    /**
     * Broadcast alert updates to dashboard
     */
    public function broadcastAlertUpdate(array $alerts, $tenantId = null): void
    {
        $this->broadcastDashboardUpdate([
            'type' => 'alert_update',
            'alerts' => $alerts
        ], $tenantId);
    }

    /**
     * Broadcast sales trend updates to dashboard
     */
    public function broadcastSalesTrendUpdate(array $salesData, $tenantId = null): void
    {
        $this->broadcastDashboardUpdate([
            'type' => 'sales_trend_update',
            'sales_data' => $salesData
        ], $tenantId);
    }

    /**
     * Send real-time alert notification
     */
    public function sendAlert(string $type, string $title, string $message, array $data = [], $userId = null, $tenantId = null): void
    {
        $notification = [
            'id' => uniqid(),
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'severity' => $this->getAlertSeverity($type),
            'created_at' => now()->toISOString()
        ];

        $this->broadcastNotification($notification, $userId, $tenantId);
    }

    /**
     * Get alert severity based on type
     */
    private function getAlertSeverity(string $type): string
    {
        return match($type) {
            'low_stock', 'overdue_invoice', 'cheque_due' => 'warning',
            'system_error', 'security_alert' => 'error',
            'payment_received', 'sale_completed' => 'success',
            default => 'info'
        };
    }
}