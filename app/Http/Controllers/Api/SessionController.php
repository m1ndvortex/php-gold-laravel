<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SessionDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    protected SessionDeviceService $sessionDeviceService;

    public function __construct(SessionDeviceService $sessionDeviceService)
    {
        $this->sessionDeviceService = $sessionDeviceService;
    }

    /**
     * Get all active sessions for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $sessions = $this->sessionDeviceService->getUserActiveSessions($user);
        $currentSessionId = session()->getId();

        $sessionsData = $sessions->map(function ($session) use ($currentSessionId) {
            return [
                'id' => $session->id,
                'session_id' => $session->session_id,
                'device_info' => $session->device_info,
                'location' => $session->location_string,
                'ip_address' => $session->ip_address,
                'browser' => $session->browser,
                'platform' => $session->platform,
                'device_type' => $session->device_type,
                'is_current' => $session->session_id === $currentSessionId,
                'last_activity' => $session->last_activity->format('Y-m-d H:i:s'),
                'last_activity_human' => $session->last_activity->diffForHumans(),
                'created_at' => $session->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'sessions' => $sessionsData,
                'total_sessions' => $sessions->count(),
            ]
        ]);
    }

    /**
     * Logout a specific session.
     */
    public function destroy(Request $request, string $sessionId): JsonResponse
    {
        $user = Auth::user();
        
        $success = $this->sessionDeviceService->logoutSession($user, $sessionId);
        
        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                    'message' => 'جلسه کاری یافت نشد',
                    'message_en' => 'Session not found'
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'جلسه کاری با موفقیت خاتمه یافت',
            'message_en' => 'Session logged out successfully'
        ]);
    }

    /**
     * Logout all other sessions except current.
     */
    public function destroyOthers(): JsonResponse
    {
        $user = Auth::user();
        $currentSessionId = session()->getId();
        
        $loggedOutCount = $this->sessionDeviceService->logoutOtherSessions($user, $currentSessionId);

        return response()->json([
            'success' => true,
            'message' => "تعداد {$loggedOutCount} جلسه کاری دیگر خاتمه یافت",
            'message_en' => "Logged out {$loggedOutCount} other sessions",
            'data' => [
                'logged_out_count' => $loggedOutCount
            ]
        ]);
    }

    /**
     * Logout all sessions including current.
     */
    public function destroyAll(): JsonResponse
    {
        $user = Auth::user();
        
        $loggedOutCount = $this->sessionDeviceService->logoutAllSessions($user);

        return response()->json([
            'success' => true,
            'message' => "تمام جلسات کاری ({$loggedOutCount}) خاتمه یافت",
            'message_en' => "All sessions ({$loggedOutCount}) logged out",
            'data' => [
                'logged_out_count' => $loggedOutCount
            ]
        ]);
    }

    /**
     * Get session timeout information.
     */
    public function timeout(): JsonResponse
    {
        $user = Auth::user();
        $currentSessionId = session()->getId();
        
        $currentSession = $user->sessions()
            ->where('session_id', $currentSessionId)
            ->active()
            ->first();

        if (!$currentSession) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                    'message' => 'جلسه کاری یافت نشد',
                    'message_en' => 'Current session not found'
                ]
            ], 404);
        }

        $timeoutMinutes = config('session.timeout', 120);
        $idleMinutes = now()->diffInMinutes($currentSession->last_activity);
        $remainingMinutes = max(0, $timeoutMinutes - $idleMinutes);

        return response()->json([
            'success' => true,
            'data' => [
                'timeout_minutes' => $timeoutMinutes,
                'idle_minutes' => $idleMinutes,
                'remaining_minutes' => $remainingMinutes,
                'remaining_seconds' => $remainingMinutes * 60,
                'last_activity' => $currentSession->last_activity->toISOString(),
                'expires_at' => $currentSession->last_activity->addMinutes($timeoutMinutes)->toISOString(),
            ]
        ]);
    }

    /**
     * Get login anomalies from session (if any).
     */
    public function anomalies(): JsonResponse
    {
        $anomalies = session()->get('login_anomalies', []);
        
        // Clear anomalies from session after retrieving
        session()->forget('login_anomalies');

        return response()->json([
            'success' => true,
            'data' => [
                'anomalies' => $anomalies,
                'has_anomalies' => !empty($anomalies),
            ]
        ]);
    }
}