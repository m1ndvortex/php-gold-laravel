<?php

namespace App\Http\Middleware;

use App\Services\SessionDeviceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    protected SessionDeviceService $sessionDeviceService;

    public function __construct(SessionDeviceService $sessionDeviceService)
    {
        $this->sessionDeviceService = $sessionDeviceService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        // Skip session timeout check in testing environment
        if (app()->environment('testing')) {
            return $next($request);
        }

        $sessionId = session()->getId();
        $user = Auth::user();
        
        // Get session timeout from config (default 120 minutes)
        $timeoutMinutes = Config::get('session.timeout', 120);
        
        // Check if current session exists and is not expired
        $currentSession = $user->sessions()
            ->where('session_id', $sessionId)
            ->active()
            ->first();

        if (!$currentSession) {
            // Session not found in database, logout user
            if (method_exists(Auth::guard(), 'logout')) {
                Auth::logout();
            }
            if (session()) {
                session()->invalidate();
                session()->regenerateToken();
            }
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SESSION_NOT_FOUND',
                        'message' => 'جلسه کاری یافت نشد',
                        'message_en' => 'Session not found'
                    ]
                ], 401);
            }
            
            return redirect()->route('login')->with('error', 'جلسه کاری شما منقضی شده است');
        }

        // Check if session has expired due to inactivity
        $lastActivity = $currentSession->last_activity;
        $idleTime = now()->diffInMinutes($lastActivity);

        if ($idleTime > $timeoutMinutes) {
            // Session expired, logout user
            $currentSession->logout();
            if (method_exists(Auth::guard(), 'logout')) {
                Auth::logout();
            }
            if (session()) {
                session()->invalidate();
                session()->regenerateToken();
            }
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SESSION_EXPIRED',
                        'message' => 'جلسه کاری به دلیل عدم فعالیت منقضی شده است',
                        'message_en' => 'Session expired due to inactivity',
                        'details' => [
                            'idle_minutes' => $idleTime,
                            'timeout_minutes' => $timeoutMinutes
                        ]
                    ]
                ], 401);
            }
            
            return redirect()->route('login')->with('error', 'جلسه کاری شما به دلیل عدم فعالیت منقضی شده است');
        }

        // Update session activity
        $this->sessionDeviceService->updateSessionActivity($sessionId);

        // Add session info to response headers for frontend
        $response = $next($request);
        
        if ($request->expectsJson()) {
            $response->headers->set('X-Session-Timeout', $timeoutMinutes * 60); // in seconds
            $response->headers->set('X-Session-Remaining', ($timeoutMinutes - $idleTime) * 60); // in seconds
        }

        return $response;
    }
}