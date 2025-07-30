<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    protected AuthService $authService;
    protected TwoFactorService $twoFactorService;

    public function __construct(AuthService $authService, TwoFactorService $twoFactorService)
    {
        $this->authService = $authService;
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * Login user.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
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

        try {
            $result = $this->authService->login(
                $request->email,
                $request->password,
                $request
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LOGIN_FAILED',
                    'message' => $e->getMessage(),
                    'message_fa' => 'ورود ناموفق',
                ],
            ], 401);
        }
    }

    /**
     * Verify two-factor authentication.
     */
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'code' => 'required|string',
            'temp_token' => 'required|string',
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

        try {
            $result = $this->authService->verifyTwoFactor(
                $request->user_id,
                $request->code,
                $request->temp_token,
                $request
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TWO_FACTOR_FAILED',
                    'message' => $e->getMessage(),
                    'message_fa' => 'کد تأیید نادرست',
                ],
            ], 401);
        }
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request);

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
                'message_fa' => 'خروج موفقیت‌آمیز',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LOGOUT_FAILED',
                    'message' => $e->getMessage(),
                    'message_fa' => 'خطا در خروج',
                ],
            ], 500);
        }
    }

    /**
     * Logout from all sessions.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $this->authService->logoutFromAllSessions($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Logged out from all sessions',
                'message_fa' => 'خروج از همه جلسات',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LOGOUT_ALL_FAILED',
                    'message' => $e->getMessage(),
                    'message_fa' => 'خطا در خروج از همه جلسات',
                ],
            ], 500);
        }
    }

    /**
     * Logout from specific session.
     */
    public function logoutSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|integer',
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

        try {
            $this->authService->logoutFromSession($request->user(), $request->session_id);

            return response()->json([
                'success' => true,
                'message' => 'Session logged out successfully',
                'message_fa' => 'جلسه با موفقیت خاتمه یافت',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LOGOUT_SESSION_FAILED',
                    'message' => $e->getMessage(),
                    'message_fa' => 'خطا در خاتمه جلسه',
                ],
            ], 500);
        }
    }

    /**
     * Get current user information.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user()->load('role.permissions'),
                'sessions' => $this->authService->getActiveSessions($request->user()),
            ],
        ]);
    }

    /**
     * Get active sessions.
     */
    public function sessions(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->authService->getActiveSessions($request->user()),
        ]);
    }
}