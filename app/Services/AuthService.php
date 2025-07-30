<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Jenssegers\Agent\Agent;

class AuthService
{
    protected Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent(null);
    }

    /**
     * Authenticate user with email and password.
     */
    public function login(string $email, string $password, Request $request): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Exception('Invalid credentials');
        }

        if (!$user->isActive()) {
            throw new \Exception('Account is inactive');
        }

        // Check if two-factor authentication is required
        if ($user->two_factor_enabled) {
            return [
                'requires_2fa' => true,
                'user_id' => $user->id,
                'temp_token' => $this->generateTempToken($user),
            ];
        }

        return $this->completeLogin($user, $request);
    }

    /**
     * Verify two-factor authentication code.
     */
    public function verifyTwoFactor(int $userId, string $code, string $tempToken, Request $request): array
    {
        $user = User::findOrFail($userId);

        if (!$this->verifyTempToken($user, $tempToken)) {
            throw new \Exception('Invalid temporary token');
        }

        if (!$this->verifyTwoFactorCode($user, $code)) {
            throw new \Exception('Invalid two-factor code');
        }

        return $this->completeLogin($user, $request);
    }

    /**
     * Complete the login process.
     */
    protected function completeLogin(User $user, Request $request): array
    {
        // Update last login information
        $user->updateLastLogin($request->ip());

        // Create session record
        $session = $this->createUserSession($user, $request);

        // Create API token with explicit expiration
        $expiresAt = now()->addDays(30);
        $token = $user->createToken('auth-token', ['*'], $expiresAt);

        // Store session ID in token
        $token->accessToken->update([
            'name' => 'auth-token-' . $session->id,
        ]);

        return [
            'user' => $user->load('role.permissions'),
            'token' => $token->plainTextToken,
            'session' => $session,
            'expires_at' => $token->accessToken->expires_at,
        ];
    }

    /**
     * Logout user from current session.
     */
    public function logout(Request $request): void
    {
        $user = $request->user();
        $token = $request->user()->currentAccessToken();

        if ($token) {
            // Find and logout session
            $sessionId = $this->extractSessionIdFromToken($token);
            if ($sessionId) {
                $session = UserSession::where('user_id', $user->id)
                    ->where('id', $sessionId)
                    ->first();
                
                if ($session) {
                    $session->logout();
                }
            }

            // Delete token
            $token->delete();
        }
    }

    /**
     * Logout user from all sessions.
     */
    public function logoutFromAllSessions(User $user): void
    {
        // Delete all tokens
        $user->tokens()->delete();

        // Logout all sessions
        $user->sessions()->active()->each(function ($session) {
            $session->logout();
        });
    }

    /**
     * Logout user from specific session.
     */
    public function logoutFromSession(User $user, int $sessionId): void
    {
        $session = $user->sessions()->where('id', $sessionId)->first();
        
        if ($session) {
            // Find and delete associated token
            $tokenName = 'auth-token-' . $sessionId;
            $user->tokens()->where('name', $tokenName)->delete();
            
            // Logout session
            $session->logout();
        }
    }

    /**
     * Create user session record.
     */
    protected function createUserSession(User $user, Request $request): UserSession
    {
        // Mark all other sessions as not current
        $user->sessions()->update(['is_current' => false]);

        $this->agent->setUserAgent($request->userAgent());

        return UserSession::create([
            'user_id' => $user->id,
            'session_id' => Str::random(40),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $this->getDeviceType(),
            'device_name' => $this->agent->device(),
            'browser' => $this->agent->browser(),
            'platform' => $this->agent->platform(),
            'location' => $this->getLocationFromIp($request->ip()),
            'is_current' => true,
            'last_activity' => now(),
        ]);
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
     * Get location information from IP address.
     */
    protected function getLocationFromIp(string $ip): ?array
    {
        // In a real implementation, you would use a service like MaxMind GeoIP
        // For now, return null or mock data
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return [
                'city' => 'Local',
                'country' => 'Local',
                'country_code' => 'LC',
            ];
        }

        return null;
    }

    /**
     * Generate temporary token for 2FA.
     */
    protected function generateTempToken(User $user): string
    {
        return encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(10)->timestamp,
            'random' => Str::random(32),
        ]);
    }

    /**
     * Verify temporary token for 2FA.
     */
    protected function verifyTempToken(User $user, string $token): bool
    {
        try {
            $data = decrypt($token);
            
            return $data['user_id'] === $user->id && 
                   $data['expires_at'] > now()->timestamp;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verify two-factor authentication code.
     */
    protected function verifyTwoFactorCode(User $user, string $code): bool
    {
        $secret = $user->getTwoFactorSecret();
        
        if (!$secret) {
            return false;
        }

        // Check if it's a recovery code
        $recoveryCodes = $user->getTwoFactorRecoveryCodes();
        if (in_array($code, $recoveryCodes)) {
            // Remove used recovery code
            $remainingCodes = array_diff($recoveryCodes, [$code]);
            $user->update([
                'two_factor_recovery_codes' => encrypt($remainingCodes),
            ]);
            return true;
        }

        // Verify TOTP code (you would use a library like pragmarx/google2fa)
        // For now, we'll accept a simple mock verification
        return $this->verifyTOTP($secret, $code);
    }

    /**
     * Verify TOTP code (mock implementation).
     */
    protected function verifyTOTP(string $secret, string $code): bool
    {
        // In a real implementation, use a library like pragmarx/google2fa
        // For now, accept any 6-digit code for testing
        return preg_match('/^\d{6}$/', $code);
    }

    /**
     * Extract session ID from token name.
     */
    protected function extractSessionIdFromToken(PersonalAccessToken $token): ?int
    {
        if (str_starts_with($token->name, 'auth-token-')) {
            return (int) str_replace('auth-token-', '', $token->name);
        }

        return null;
    }

    /**
     * Get active sessions for user.
     */
    public function getActiveSessions(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->sessions()
            ->active()
            ->orderBy('last_activity', 'desc')
            ->get();
    }

    /**
     * Update session activity.
     */
    public function updateSessionActivity(Request $request): void
    {
        $user = $request->user();
        $token = $request->user()->currentAccessToken();

        if ($token && $user) {
            $sessionId = $this->extractSessionIdFromToken($token);
            if ($sessionId) {
                $session = UserSession::where('user_id', $user->id)
                    ->where('id', $sessionId)
                    ->first();
                
                if ($session) {
                    $session->updateActivity();
                }
            }
        }
    }
}