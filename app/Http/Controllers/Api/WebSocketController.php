<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebSocketService;
use App\Services\DashboardService;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebSocketController extends Controller
{
    protected $webSocketService;
    protected $dashboardService;
    protected $alertService;

    public function __construct(
        WebSocketService $webSocketService,
        DashboardService $dashboardService,
        AlertService $alertService
    ) {
        $this->webSocketService = $webSocketService;
        $this->dashboardService = $dashboardService;
        $this->alertService = $alertService;
    }

    /**
     * Broadcast dashboard updates manually
     */
    public function broadcastDashboardUpdate(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'today');
            $kpis = $this->dashboardService->getKPIsWithBroadcast($period);
            
            return response()->json([
                'success' => true,
                'message' => 'Dashboard update broadcasted successfully',
                'data' => $kpis
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to broadcast dashboard update',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Broadcast sales trend updates manually
     */
    public function broadcastSalesTrend(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', '30_days');
            $salesData = $this->dashboardService->getSalesTrendWithBroadcast($period);
            
            return response()->json([
                'success' => true,
                'message' => 'Sales trend update broadcasted successfully',
                'data' => $salesData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to broadcast sales trend update',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Broadcast alert updates manually
     */
    public function broadcastAlerts(): JsonResponse
    {
        try {
            $alerts = $this->alertService->getAllAlertsWithBroadcast();
            
            return response()->json([
                'success' => true,
                'message' => 'Alert updates broadcasted successfully',
                'data' => $alerts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to broadcast alert updates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send custom notification
     */
    public function sendNotification(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'array',
            'user_id' => 'nullable|integer|exists:users,id'
        ]);

        try {
            $this->webSocketService->sendAlert(
                $request->type,
                $request->title,
                $request->message,
                $request->data ?? [],
                $request->user_id
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test WebSocket connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            $testData = [
                'type' => 'connection_test',
                'message' => 'WebSocket connection test successful',
                'timestamp' => now()->toISOString(),
                'user_id' => auth()->id(),
                'tenant_id' => tenant_context()?->id
            ];

            $this->webSocketService->broadcastNotification($testData);
            
            return response()->json([
                'success' => true,
                'message' => 'WebSocket test completed successfully',
                'data' => $testData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'WebSocket test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get WebSocket connection info
     */
    public function getConnectionInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => auth()->id(),
                'tenant_id' => tenant_context()?->id,
                'channels' => [
                    'tenant' => 'tenant.' . (tenant_context()?->id ?? 'unknown'),
                    'user' => 'user.' . auth()->id(),
                    'dashboard' => 'dashboard.' . (tenant_context()?->id ?? 'unknown'),
                    'inventory' => 'inventory.' . (tenant_context()?->id ?? 'unknown'),
                    'notifications' => 'notifications.' . (tenant_context()?->id ?? 'unknown'),
                ],
                'broadcast_driver' => config('broadcasting.default'),
                'pusher_config' => [
                    'key' => config('broadcasting.connections.pusher.key'),
                    'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                    'host' => config('broadcasting.connections.pusher.options.host'),
                    'port' => config('broadcasting.connections.pusher.options.port'),
                    'scheme' => config('broadcasting.connections.pusher.options.scheme'),
                ]
            ]
        ]);
    }
}