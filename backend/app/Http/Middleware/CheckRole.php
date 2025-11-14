<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'error' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Superadmin can access everything
        if ($user->isSuperadmin()) {
            return $next($request);
        }

        // Get tenant context
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant context is required',
                'error' => 'TENANT_REQUIRED'
            ], 403);
        }

        // Get user's role in this tenant
        $tenantUser = $user->tenantUsers()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (!$tenantUser) {
            return response()->json([
                'message' => 'You do not have access to this tenant',
                'error' => 'TENANT_ACCESS_DENIED'
            ], 403);
        }

        // Check if user has one of the required roles
        if (!in_array($tenantUser->role, $roles)) {
            return response()->json([
                'message' => 'You do not have permission to perform this action',
                'error' => 'INSUFFICIENT_PERMISSIONS',
                'required_roles' => $roles,
                'your_role' => $tenantUser->role
            ], 403);
        }

        // Attach tenant user info to request for use in controllers
        $request->merge(['tenant_user' => $tenantUser]);

        return $next($request);
    }
}
