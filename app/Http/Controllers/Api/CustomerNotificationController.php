<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerNotification;
use App\Services\CustomerNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerNotificationController extends Controller
{
    public function __construct(
        private CustomerNotificationService $notificationService
    ) {}

    /**
     * Get all customer notifications with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerNotification::with('customer');

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->get('customer_id'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->get('type'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('scheduled_at', [
                $request->get('start_date'),
                $request->get('end_date')
            ]);
        }

        $perPage = $request->get('per_page', 15);
        $notifications = $query->orderBy('scheduled_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Create a custom occasion notification
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'title' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'message' => 'required|string',
            'message_en' => 'nullable|string',
            'scheduled_at' => 'required|date|after_or_equal:today',
            'channels' => 'nullable|array',
            'channels.*' => 'string|in:email,sms,whatsapp,system',
            'metadata' => 'nullable|array',
        ]);

        try {
            $customer = Customer::findOrFail($validated['customer_id']);
            
            $notification = $this->notificationService->createOccasionNotification($customer, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Notification created successfully',
                'data' => $notification,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get notification details
     */
    public function show(CustomerNotification $notification): JsonResponse
    {
        $notification->load('customer');

        return response()->json([
            'success' => true,
            'data' => $notification,
        ]);
    }

    /**
     * Update notification (only pending notifications)
     */
    public function update(Request $request, CustomerNotification $notification): JsonResponse
    {
        if (!$notification->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending notifications can be updated',
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'message' => 'required|string',
            'message_en' => 'nullable|string',
            'scheduled_at' => 'required|date|after_or_equal:today',
            'channels' => 'nullable|array',
            'channels.*' => 'string|in:email,sms,whatsapp,system',
            'metadata' => 'nullable|array',
        ]);

        try {
            $notification->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Notification updated successfully',
                'data' => $notification,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel notification
     */
    public function cancel(CustomerNotification $notification): JsonResponse
    {
        try {
            $cancelled = $this->notificationService->cancelNotification($notification->id);

            if ($cancelled) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification cancelled successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Notification cannot be cancelled',
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pending notifications
     */
    public function pending(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        
        $notifications = CustomerNotification::with('customer')
            ->pending()
            ->due()
            ->orderBy('scheduled_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Get customer notification history
     */
    public function customerHistory(Request $request, int $customerId): JsonResponse
    {
        $limit = $request->get('limit', 50);
        
        try {
            $notifications = $this->notificationService->getCustomerNotificationHistory($customerId, $limit);

            return response()->json([
                'success' => true,
                'data' => $notifications,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get notification history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create birthday notifications for upcoming birthdays
     */
    public function createBirthdayNotifications(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days_ahead' => 'nullable|integer|min:1|max:30',
        ]);

        $daysAhead = $validated['days_ahead'] ?? 7;

        try {
            $created = $this->notificationService->createBirthdayNotifications($daysAhead);

            return response()->json([
                'success' => true,
                'message' => "Created {$created} birthday notifications",
                'data' => ['created' => $created],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create birthday notifications',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create overdue payment notifications
     */
    public function createOverdueNotifications(): JsonResponse
    {
        try {
            $created = $this->notificationService->createOverduePaymentNotifications();

            return response()->json([
                'success' => true,
                'message' => "Created {$created} overdue payment notifications",
                'data' => ['created' => $created],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create overdue notifications',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create credit limit exceeded notifications
     */
    public function createCreditLimitNotifications(): JsonResponse
    {
        try {
            $created = $this->notificationService->createCreditLimitNotifications();

            return response()->json([
                'success' => true,
                'message' => "Created {$created} credit limit notifications",
                'data' => ['created' => $created],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create credit limit notifications',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process and send pending notifications
     */
    public function processPending(): JsonResponse
    {
        try {
            $processed = $this->notificationService->processPendingNotifications();

            return response()->json([
                'success' => true,
                'message' => "Processed {$processed} notifications",
                'data' => ['processed' => $processed],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process notifications',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_notifications' => CustomerNotification::count(),
                'pending_notifications' => CustomerNotification::pending()->count(),
                'sent_notifications' => CustomerNotification::sent()->count(),
                'failed_notifications' => CustomerNotification::failed()->count(),
                'due_notifications' => CustomerNotification::pending()->due()->count(),
                'notifications_by_type' => CustomerNotification::selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'recent_notifications' => CustomerNotification::with('customer')
                    ->latest()
                    ->limit(5)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}