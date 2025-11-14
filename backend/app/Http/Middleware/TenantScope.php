<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantScope
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Superadmin can access global endpoints, skip tenant scoping
        if ($user && $user->isSuperadmin()) {
            return $next($request);
        }

        // For non-superadmin users, require tenant context
        if ($user && !$user->isSuperadmin()) {
            // Get tenant from header or request
            $tenantId = $request->header('X-Tenant-ID') ?? $request->input('tenant_id');

            if (!$tenantId) {
                // Try to get from user's active tenant
                $activeTenant = $user->getActiveTenant();
                if ($activeTenant) {
                    $tenantId = $activeTenant->tenant_id;
                }
            }

            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant context is required',
                    'error' => 'TENANT_REQUIRED'
                ], 403);
            }

            // Verify user has access to this tenant
            $hasAccess = $user->tenantUsers()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'message' => 'You do not have access to this tenant',
                    'error' => 'TENANT_ACCESS_DENIED'
                ], 403);
            }

            // Set tenant context for the request
            $request->merge(['tenant_id' => $tenantId]);
            app()->instance('tenant_id', $tenantId);
        }

        return $next($request);
    }
}
