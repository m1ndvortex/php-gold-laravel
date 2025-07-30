<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class SessionDeviceService
{
    protected Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }

    /**
     * Create a new session record for the user.
     */
    public function createSession(User $user, Request $request, string $sessionId): UserSession
    {
        // Mark all other sessions as not current
        $user->sessions()->update(['is_current' => false]);

        // Parse user agent
        $this->agent->setUserAgent($request->userAgent());

        // Get location data
        $location = $this->getLocationFromIP($request->ip());

        return UserSession::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $this->getDeviceType(),
            'device_name' => $this->getDeviceName(),
            'browser' => $this->agent->browser() . ' ' . $this->agent->version($this->agent->browser()),
            'platform' => $this->agent->platform() . ' ' . $this->agent->version($this->agent->platform()),
            'location' => $location,
            'is_current' => true,
            'last_activity' => now(),
        ]);
    }

    /**
     * Update session activity.
     */
    public function updateSessionActivity(string $sessionId): void
    {
        UserSession::where('session_id', $sessionId)
            ->whereNull('logged_out_at')
            ->update(['last_activity' => now()]);
    }

    /**
     * Get all active sessions for a user.
     */
    public function getUserActiveSessions(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->sessions()
            ->active()
            ->orderBy('last_activity', 'desc')
            ->get();
    }

    /**
     * Logout a specific session.
     */
    public function logoutSession(User $user, string $sessionId): bool
    {
        $session = $user->sessions()
            ->where('session_id', $sessionId)
            ->active()
            ->first();

        if ($session) {
            $session->logout();
            
            // If this was the current session, clear Laravel session
            if ($session->is_current) {
                session()->invalidate();
                session()->regenerateToken();
            }
            
            return true;
        }

        return false;
    }

    /**
     * Logout all sessions except current.
     */
    public function logoutOtherSessions(User $user, string $currentSessionId): int
    {
        $sessions = $user->sessions()
            ->active()
            ->where('session_id', '!=', $currentSessionId)
            ->get();

        foreach ($sessions as $session) {
            $session->logout();
        }

        return $sessions->count();
    }

    /**
     * Logout all sessions for a user.
     */
    public function logoutAllSessions(User $user): int
    {
        $sessions = $user->sessions()->active()->get();

        foreach ($sessions as $session) {
            $session->logout();
        }

        // Clear current Laravel session
        session()->invalidate();
        session()->regenerateToken();

        return $sessions->count();
    }

    /**
     * Clean up expired sessions.
     */
    public function cleanupExpiredSessions(int $maxIdleMinutes = 120): int
    {
        $expiredTime = now()->subMinutes($maxIdleMinutes);
        
        $expiredSessions = UserSession::active()
            ->where('last_activity', '<', $expiredTime)
            ->get();

        foreach ($expiredSessions as $session) {
            $session->logout();
        }

        return $expiredSessions->count();
    }

    /**
     * Detect login anomalies.
     */
    public function detectLoginAnomalies(User $user, Request $request): array
    {
        $anomalies = [];
        $currentIP = $request->ip();
        $currentLocation = $this->getLocationFromIP($currentIP);

        // Get recent sessions (last 30 days)
        $recentSessions = $user->sessions()
            ->where('created_at', '>', now()->subDays(30))
            ->get();

        if ($recentSessions->isEmpty()) {
            return $anomalies;
        }

        // Check for new IP address
        $knownIPs = $recentSessions->pluck('ip_address')->unique();
        if (!$knownIPs->contains($currentIP)) {
            $anomalies[] = [
                'type' => 'new_ip',
                'message' => 'Login from new IP address',
                'details' => [
                    'ip' => $currentIP,
                    'location' => $currentLocation,
                ]
            ];
        }

        // Check for unusual location
        if ($currentLocation && isset($currentLocation['country'])) {
            $knownCountries = $recentSessions
                ->whereNotNull('location')
                ->pluck('location')
                ->map(fn($loc) => $loc['country'] ?? null)
                ->filter()
                ->unique();

            if ($knownCountries->isNotEmpty() && !$knownCountries->contains($currentLocation['country'])) {
                $anomalies[] = [
                    'type' => 'new_location',
                    'message' => 'Login from new country',
                    'details' => [
                        'country' => $currentLocation['country'],
                        'city' => $currentLocation['city'] ?? 'Unknown',
                    ]
                ];
            }
        }

        // Check for unusual device
        $this->agent->setUserAgent($request->userAgent());
        $currentDevice = $this->getDeviceType();
        $currentBrowser = $this->agent->browser();
        
        $knownDevices = $recentSessions->pluck('device_type')->unique();
        $knownBrowsers = $recentSessions->pluck('browser')
            ->map(fn($browser) => explode(' ', $browser)[0])
            ->unique();

        if (!$knownDevices->contains($currentDevice)) {
            $anomalies[] = [
                'type' => 'new_device',
                'message' => 'Login from new device type',
                'details' => [
                    'device_type' => $currentDevice,
                    'browser' => $currentBrowser,
                ]
            ];
        }

        // Check for rapid successive logins from different locations
        $recentLogins = $recentSessions
            ->where('created_at', '>', now()->subHours(1))
            ->sortBy('created_at');

        if ($recentLogins->count() > 1) {
            $lastLogin = $recentLogins->last();
            if ($lastLogin && $lastLogin->ip_address !== $currentIP) {
                $anomalies[] = [
                    'type' => 'rapid_location_change',
                    'message' => 'Rapid login from different location',
                    'details' => [
                        'previous_ip' => $lastLogin->ip_address,
                        'current_ip' => $currentIP,
                        'time_difference' => now()->diffInMinutes($lastLogin->created_at),
                    ]
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Get device type from user agent.
     */
    protected function getDeviceType(): string
    {
        if ($this->agent->isMobile()) {
            return 'mobile';
        } elseif ($this->agent->isTablet()) {
            return 'tablet';
        } elseif ($this->agent->isDesktop()) {
            return 'desktop';
        }

        return 'unknown';
    }

    /**
     * Get device name from user agent.
     */
    protected function getDeviceName(): ?string
    {
        $device = $this->agent->device();
        
        if ($device && $device !== 'WebKit') {
            return $device;
        }

        // Try to get more specific device info
        if ($this->agent->isMobile()) {
            return $this->agent->platform();
        }

        return null;
    }

    /**
     * Get location information from IP address.
     */
    protected function getLocationFromIP(string $ip): ?array
    {
        // Skip for local/private IPs
        if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return [
                'city' => 'Local',
                'country' => 'Local',
                'country_code' => 'LC',
            ];
        }

        try {
            // Using a free IP geolocation service
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}");
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'success') {
                    return [
                        'city' => $data['city'] ?? null,
                        'country' => $data['country'] ?? null,
                        'country_code' => $data['countryCode'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'timezone' => $data['timezone'] ?? null,
                        'lat' => $data['lat'] ?? null,
                        'lon' => $data['lon'] ?? null,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get location for IP: ' . $ip, ['error' => $e->getMessage()]);
        }

        return null;
    }
}