<?php

namespace App\Http\Middleware;

use App\Services\SessionDeviceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

class LoginAnomalyDetection
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
        // Only check on login attempts
        if (!$this->isLoginAttempt($request)) {
            return $next($request);
        }

        $response = $next($request);

        // Only proceed if login was successful
        if (Auth::check() && $this->isSuccessfulLogin($response)) {
            $user = Auth::user();
            
            // Detect anomalies
            $anomalies = $this->sessionDeviceService->detectLoginAnomalies($user, $request);
            
            if (!empty($anomalies)) {
                $this->handleAnomalies($user, $request, $anomalies);
            }
        }

        return $response;
    }

    /**
     * Check if this is a login attempt.
     */
    protected function isLoginAttempt(Request $request): bool
    {
        return $request->is('api/auth/login') || 
               $request->is('login') ||
               ($request->isMethod('POST') && $request->has(['email', 'password']));
    }

    /**
     * Check if login was successful.
     */
    protected function isSuccessfulLogin(Response $response): bool
    {
        if ($response->isSuccessful()) {
            return true;
        }

        // For JSON responses, check the response content
        if ($response->headers->get('content-type') === 'application/json') {
            $content = json_decode($response->getContent(), true);
            return isset($content['success']) && $content['success'] === true;
        }

        return false;
    }

    /**
     * Handle detected anomalies.
     */
    protected function handleAnomalies($user, Request $request, array $anomalies): void
    {
        // Log the anomalies
        Log::warning('Login anomalies detected', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'anomalies' => $anomalies,
            'timestamp' => now()->toISOString(),
        ]);

        // Store anomaly record in session for potential user notification
        session()->put('login_anomalies', $anomalies);

        // You could also send notifications here
        // $this->sendAnomalyNotification($user, $anomalies);
    }

    /**
     * Send anomaly notification to user (optional implementation).
     */
    protected function sendAnomalyNotification($user, array $anomalies): void
    {
        // This could send email/SMS notifications
        // Implementation depends on your notification preferences
        
        $anomalyTypes = collect($anomalies)->pluck('type')->implode(', ');
        
        Log::info('Anomaly notification would be sent', [
            'user_id' => $user->id,
            'anomaly_types' => $anomalyTypes,
        ]);
        
        // Example: Send email notification
        // Mail::to($user->email)->send(new LoginAnomalyMail($user, $anomalies));
    }
}