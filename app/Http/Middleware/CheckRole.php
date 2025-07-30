<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
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

        if (!$user->role || !in_array($user->role->name, $roles)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_ROLE',
                    'message' => 'Insufficient role permissions',
                    'message_fa' => 'نقش کاربری مناسب نیست',
                    'required_roles' => $roles,
                    'user_role' => $user->role?->name,
                ],
            ], 403);
        }

        return $next($request);
    }
}