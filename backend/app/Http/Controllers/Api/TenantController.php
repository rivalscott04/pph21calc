<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    /**
     * List all tenants (Superadmin only)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->isSuperadmin()) {
            return response()->json([
                'message' => 'Only superadmin can access this endpoint',
                'error' => 'UNAUTHORIZED'
            ], 403);
        }

        $tenants = Tenant::withCount('tenantUsers')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($tenants);
    }

    /**
     * Create new tenant (Superadmin only)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->isSuperadmin()) {
            return response()->json([
                'message' => 'Only superadmin can access this endpoint',
                'error' => 'UNAUTHORIZED'
            ], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:tenants,code',
            'name' => 'required|string|max:255',
            'npwp_pemotong' => 'nullable|string|max:20',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $tenant = Tenant::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'npwp_pemotong' => $validated['npwp_pemotong'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json($tenant, 201);
    }

    /**
     * Get tenant detail (Superadmin only)
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || !$user->isSuperadmin()) {
            return response()->json([
                'message' => 'Only superadmin can access this endpoint',
                'error' => 'UNAUTHORIZED'
            ], 403);
        }

        $tenant = Tenant::with('tenantUsers.user')
            ->findOrFail($id);

        return response()->json($tenant);
    }

    /**
     * Update tenant (Superadmin only)
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || !$user->isSuperadmin()) {
            return response()->json([
                'message' => 'Only superadmin can access this endpoint',
                'error' => 'UNAUTHORIZED'
            ], 403);
        }

        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('tenants')->ignore($tenant->id)],
            'name' => 'sometimes|string|max:255',
            'npwp_pemotong' => 'nullable|string|max:20',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        $tenant->update($validated);

        return response()->json($tenant);
    }

    /**
     * List users in tenant (Superadmin only)
     */
    public function users(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || !$user->isSuperadmin()) {
            return response()->json([
                'message' => 'Only superadmin can access this endpoint',
                'error' => 'UNAUTHORIZED'
            ], 403);
        }

        $tenant = Tenant::findOrFail($id);

        $tenantUsers = TenantUser::where('tenant_id', $tenant->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($tenantUsers);
    }

    /**
     * Create user in tenant (Superadmin only)
     */
    public function createUser(Request $request, $id)
    {
        $user = $request->user();

        if (!$user || !$user->isSuperadmin()) {
            return response()->json([
                'message' => 'Only superadmin can access this endpoint',
                'error' => 'UNAUTHORIZED'
            ], 403);
        }

        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:TENANT_ADMIN,HR,FINANCE,VIEWER',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        // Create user
        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_superadmin' => false,
            'status' => $validated['status'] ?? 'active',
        ]);

        // Create tenant user relationship
        $tenantUser = TenantUser::create([
            'user_id' => $newUser->id,
            'tenant_id' => $tenant->id,
            'role' => $validated['role'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'user' => $newUser,
            'tenant_user' => $tenantUser->load('user', 'tenant'),
        ], 201);
    }
}
