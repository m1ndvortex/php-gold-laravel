<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TwoFactorController extends Controller
{
    protected TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * Enable two-factor authentication.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->twoFactorService->isEnabled($user)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TWO_FACTOR_ALREADY_ENABLED',
                    'message' => 'Two-factor authentication is already enabled',
                    'message_fa' => 'احراز هویت دو مرحله‌ای قبلاً فعال شده است',
                ],
            ], 400);
        }

        try {
            $result = $this->twoFactorService->enable($user);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Two-factor authentication enabled successfully',
                'message_fa' => 'احراز هویت دو مرحله‌ای با موفقیت فعال شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TWO_FACTOR_ENABLE_FAILED',
                    'message' => $e->getMessage(),
                    'message_fa' => 'خطا در فعال‌سازی احراز هویت دو مرحله‌ای',
                ],
            ], 500);
        }
    }

    /**
     * Disable two-factor authentication.
     */
    public function disable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'message_fa' => 'خطا در اعتبارسنجی',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $user = $request->user();

        // Verify password
        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PASSWORD',
                    'message' => 'Invalid password',
                    'message_fa' => 'رمز عبور نادرست',
                ],
            ], 401);
        }

        if (!$this->twoFactorService->isEnabled($user)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TWO_FACTOR_NOT_ENABLED',
                    'message' => 'Two-factor authentication is not enabled',
                    'message_fa' => 'احراز هویت دو مرحله‌ای فعال نیست',
                ],
            ], 400);
        }

        try {
            $this->twoFactorService->disable($user);

            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication disabled successfully',
                'message_fa' => 'احراز هویت دو مرحله‌ای با موفقیت غیرفعال شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TWO_FACTOR_DISABLE_FAILED',
                    'message' => $e->getMessage(),
                    'message_fa' => 'خطا در غیرفعال‌سازی احراز هویت دو مرحله‌ای',
                ],
            ], 500);
        }
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'message_fa' => 'خطا در اعتبارسنجی',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $user = $request->user();

        // Verify password
        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PASSWORD',
                    'message' => 'Invalid password',
                    'message_fa' => 'رمز عبور نادرست',
                ],
            ], 401);
        }

        if (!$this->twoFactorService->isEnabled($user)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TWO_FACTOR_NOT_ENABLED',
                    'message' => 'Two-factor authentication is not enabled',
                    'message_fa' => 'احراز هویت دو مرحله‌ای فعال نیست',
                ],
            ], 400);
        }

        try {
            $recoveryCodes = $this->twoFactorService->regenerateRecoveryCodes($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'recovery_codes' => $recoveryCodes,
                ],
                'message' => 'Recovery codes regenerated successfully',
                'message_fa' => 'کدهای بازیابی با موفقیت تولید شدند',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RECOVERY_CODES_FAILED',
                    'message' => $e->getMessage(),
                    'message_fa' => 'خطا در تولید کدهای بازیابی',
                ],
            ], 500);
        }
    }

    /**
     * Get two-factor authentication status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $this->twoFactorService->isEnabled($user),
                'recovery_codes_count' => $this->twoFactorService->getRemainingRecoveryCodesCount($user),
            ],
        ]);
    }
}