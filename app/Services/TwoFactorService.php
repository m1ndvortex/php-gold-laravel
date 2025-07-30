<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class TwoFactorService
{
    /**
     * Enable two-factor authentication for user.
     */
    public function enable(User $user): array
    {
        $secret = $this->generateSecret();
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->enableTwoFactor($secret, $recoveryCodes);

        return [
            'secret' => $secret,
            'qr_code_url' => $this->getQRCodeUrl($user, $secret),
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * Disable two-factor authentication for user.
     */
    public function disable(User $user): void
    {
        $user->disableTwoFactor();
    }

    /**
     * Generate new recovery codes.
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $recoveryCodes = $this->generateRecoveryCodes();
        
        $user->update([
            'two_factor_recovery_codes' => encrypt($recoveryCodes),
        ]);

        return $recoveryCodes;
    }

    /**
     * Generate a new secret key.
     */
    protected function generateSecret(): string
    {
        // In a real implementation, use a proper TOTP library
        // For now, generate a random 32-character base32 string
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $secret;
    }

    /**
     * Generate recovery codes.
     */
    protected function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
        }

        return $codes;
    }

    /**
     * Get QR code URL for Google Authenticator.
     */
    protected function getQRCodeUrl(User $user, string $secret): string
    {
        $appName = config('app.name', 'Jewelry Platform');
        $email = $user->email;
        
        $qrCodeUrl = 'otpauth://totp/' . urlencode($appName . ':' . $email) . 
                     '?secret=' . $secret . 
                     '&issuer=' . urlencode($appName);

        // Return Google Charts QR code URL
        return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($qrCodeUrl);
    }

    /**
     * Verify TOTP code.
     */
    public function verifyCode(User $user, string $code): bool
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

        // Verify TOTP code
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
     * Check if user has two-factor enabled.
     */
    public function isEnabled(User $user): bool
    {
        return $user->two_factor_enabled;
    }

    /**
     * Get remaining recovery codes count.
     */
    public function getRemainingRecoveryCodesCount(User $user): int
    {
        return count($user->getTwoFactorRecoveryCodes());
    }
}