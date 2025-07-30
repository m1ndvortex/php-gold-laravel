<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required',
                    'message_fa' => 'احراز هویت مورد نیاز است',
                ],
            ], 401);
        }

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_PERMISSIONS',
                    'message' => 'Insufficient permissions',
                    'message_fa' => 'دسترسی کافی نیست',
                    'required_permission' => $permission,
                ],
            ], 403);
        }

        return $next($request);
    }
}