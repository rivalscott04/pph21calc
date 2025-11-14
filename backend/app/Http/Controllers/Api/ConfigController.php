<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfigBranding;
use App\Models\ConfigModule;
use App\Models\IdentifierScheme;
use App\Models\PersonIdentifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConfigController extends Controller
{
    /**
     * Get modules config (per tenant)
     */
    public function getModules(Request $request)
    {
        $user = $request->user();
        
        // Superadmin can access any tenant's config via query param
        if ($user->isSuperadmin() && $request->has('tenant_id')) {
            $tenantId = $request->input('tenant_id');
        } else {
            // For non-superadmin, get from middleware context
            $tenantId = $request->input('tenant_id') 
                ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
            
            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant context is required',
                    'error' => 'TENANT_REQUIRED'
                ], 403);
            }
        }

        $config = ConfigModule::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'core_payroll' => true,
                'audit_trail' => true,
                'coretax_integration' => false,
                'compliance_ojk' => false,
                'compliance_pdp' => false,
                'bpjs_integration' => false,
                'syariah_extension' => false,
            ]
        );

        return response()->json($config);
    }

    /**
     * Update modules config
     */
    public function updateModules(Request $request)
    {
        $user = $request->user();
        
        // Superadmin can update any tenant's config via query param
        if ($user->isSuperadmin() && $request->has('tenant_id')) {
            $tenantId = $request->input('tenant_id');
        } else {
            // For non-superadmin, get from middleware context
            $tenantId = $request->input('tenant_id') 
                ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
            
            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant context is required',
                    'error' => 'TENANT_REQUIRED'
                ], 403);
            }
        }

        // Only TENANT_ADMIN or superadmin can update modules
        if (!$user->isSuperadmin()) {
            $role = $user->getRoleInTenant($tenantId);
            if ($role !== 'TENANT_ADMIN') {
                return response()->json([
                    'message' => 'Only tenant admin can update modules',
                    'error' => 'INSUFFICIENT_PERMISSIONS'
                ], 403);
            }
        }

        $validated = $request->validate([
            'core_payroll' => 'sometimes|boolean',
            'coretax_integration' => 'sometimes|boolean',
            'compliance_ojk' => 'sometimes|boolean',
            'compliance_pdp' => 'sometimes|boolean',
            'audit_trail' => 'sometimes|boolean',
            'bpjs_integration' => 'sometimes|boolean',
            'syariah_extension' => 'sometimes|boolean',
        ]);

        $config = ConfigModule::updateOrCreate(
            ['tenant_id' => $tenantId],
            $validated
        );

        return response()->json($config);
    }

    /**
     * Get branding config
     */
    public function getBranding(Request $request)
    {
        $user = $request->user();
        
        // Superadmin can access any tenant's config via query param
        if ($user->isSuperadmin() && $request->has('tenant_id')) {
            $tenantId = $request->input('tenant_id');
        } else {
            // For non-superadmin, get from middleware context
            $tenantId = $request->input('tenant_id') 
                ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
            
            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant context is required',
                    'error' => 'TENANT_REQUIRED'
                ], 403);
            }
        }

        $config = ConfigBranding::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'primary' => '#0ea5e9',
                'secondary' => '#10b981',
                'accent' => '#f59e0b',
                'neutral' => '#3d4451',
                'base100' => '#ffffff',
            ]
        );

        return response()->json($config);
    }

    /**
     * Update branding config
     */
    public function updateBranding(Request $request)
    {
        $user = $request->user();
        
        // Superadmin can update any tenant's config via query param
        if ($user->isSuperadmin() && $request->has('tenant_id')) {
            $tenantId = $request->input('tenant_id');
        } else {
            // For non-superadmin, get from middleware context
            $tenantId = $request->input('tenant_id') 
                ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
            
            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant context is required',
                    'error' => 'TENANT_REQUIRED'
                ], 403);
            }
        }

        // Only TENANT_ADMIN or superadmin can update branding
        if (!$user->isSuperadmin()) {
            $role = $user->getRoleInTenant($tenantId);
            if ($role !== 'TENANT_ADMIN') {
                return response()->json([
                    'message' => 'Only tenant admin can update branding',
                    'error' => 'INSUFFICIENT_PERMISSIONS'
                ], 403);
            }
        }

        $validated = $request->validate([
            'primary' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'neutral' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'base100' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $config = ConfigBranding::updateOrCreate(
            ['tenant_id' => $tenantId],
            $validated
        );

        return response()->json($config);
    }

    /**
     * Get identifier schemes
     */
    public function getIdentifierSchemes(Request $request)
    {
        $user = $request->user();
        
        // Superadmin can access any tenant's schemes via query param
        if ($user->isSuperadmin() && $request->has('tenant_id')) {
            $tenantId = $request->input('tenant_id');
        } else {
            // For non-superadmin, get from middleware context
            $tenantId = $request->input('tenant_id') 
                ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
            
            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant context is required',
                    'error' => 'TENANT_REQUIRED'
                ], 403);
            }
        }

        $query = IdentifierScheme::query();

        // Filter by tenant (include global schemes with tenant_id = null)
        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            });
        }

        // Filter by entity type if provided
        if ($request->has('entity')) {
            $query->where('entity_type', $request->input('entity'));
        }

        $schemes = $query->orderBy('code')->get();

        // Transform to frontend format
        $transformed = $schemes->map(function ($scheme) {
            return [
                'id' => $scheme->id,
                'code' => $scheme->code,
                'label' => $scheme->label,
                'entity_type' => $scheme->entity_type,
                'normalize' => $scheme->normalize_rule,
                'patterns' => $scheme->regex_pattern ? [$scheme->regex_pattern] : [],
                'length' => [
                    'min' => $scheme->length_min,
                    'max' => $scheme->length_max,
                ],
                'example' => $scheme->example,
                'checksum_type' => $scheme->checksum_type,
            ];
        });

        return response()->json([
            'entity' => $request->input('entity'),
            'schemes' => $transformed,
        ]);
    }

    /**
     * Create identifier scheme
     */
    public function createIdentifierScheme(Request $request)
    {
        $user = $request->user();
        
        // Superadmin can create schemes for any tenant via query param
        if ($user->isSuperadmin() && $request->has('tenant_id')) {
            $tenantId = $request->input('tenant_id');
        } else {
            // For non-superadmin, get from middleware context
            $tenantId = $request->input('tenant_id') 
                ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
            
            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant context is required',
                    'error' => 'TENANT_REQUIRED'
                ], 403);
            }
        }

        // Only TENANT_ADMIN or superadmin can create schemes
        if (!$user->isSuperadmin()) {
            $role = $user->getRoleInTenant($tenantId);
            if ($role !== 'TENANT_ADMIN') {
                return response()->json([
                    'message' => 'Only tenant admin can create identifier schemes',
                    'error' => 'INSUFFICIENT_PERMISSIONS'
                ], 403);
            }
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'entity_type' => 'nullable|string|max:50',
            'regex_pattern' => 'nullable|string',
            'length_min' => 'nullable|integer|min:1',
            'length_max' => 'nullable|integer|min:1|gte:length_min',
            'normalize_rule' => 'nullable|string|in:NUMERIC,ALNUM,UPPER,NONE',
            'example' => 'nullable|string|max:50',
            'checksum_type' => 'nullable|string|in:LUHN,MOD_N,NONE',
        ]);

        // Check unique code per tenant (or globally if tenant_id is null)
        $exists = IdentifierScheme::where('tenant_id', $tenantId)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Identifier scheme code already exists',
                'error' => 'DUPLICATE_CODE'
            ], 422);
        }

        $scheme = IdentifierScheme::create([
            'tenant_id' => $tenantId,
            'code' => $validated['code'],
            'label' => $validated['label'],
            'entity_type' => $validated['entity_type'] ?? null,
            'regex_pattern' => $validated['regex_pattern'] ?? null,
            'length_min' => $validated['length_min'] ?? null,
            'length_max' => $validated['length_max'] ?? null,
            'normalize_rule' => $validated['normalize_rule'] ?? 'NONE',
            'example' => $validated['example'] ?? null,
            'checksum_type' => $validated['checksum_type'] ?? 'NONE',
        ]);

        return response()->json($scheme, 201);
    }

    /**
     * Update identifier scheme
     */
    public function updateIdentifierScheme(Request $request, $id)
    {
        $user = $request->user();
        $scheme = IdentifierScheme::findOrFail($id);

        // Check if user has access to this scheme's tenant
        if (!$user->isSuperadmin()) {
            $tenantId = $request->input('tenant_id') 
                ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

            // For non-global schemes, check tenant access
            if ($scheme->tenant_id && $scheme->tenant_id != $tenantId) {
                return response()->json([
                    'message' => 'You do not have access to this scheme',
                    'error' => 'ACCESS_DENIED'
                ], 403);
            }

            // For global schemes (tenant_id = null), only superadmin can update
            if (!$scheme->tenant_id) {
                return response()->json([
                    'message' => 'Only superadmin can update global schemes',
                    'error' => 'INSUFFICIENT_PERMISSIONS'
                ], 403);
            }

            // Only TENANT_ADMIN can update tenant-specific schemes
            $role = $user->getRoleInTenant($tenantId);
            if ($role !== 'TENANT_ADMIN') {
                return response()->json([
                    'message' => 'Only tenant admin can update identifier schemes',
                    'error' => 'INSUFFICIENT_PERMISSIONS'
                ], 403);
            }
        }

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('identifier_schemes')->where('tenant_id', $scheme->tenant_id)->ignore($scheme->id)],
            'label' => 'sometimes|string|max:255',
            'entity_type' => 'nullable|string|max:50',
            'regex_pattern' => 'nullable|string',
            'length_min' => 'nullable|integer|min:1',
            'length_max' => 'nullable|integer|min:1|gte:length_min',
            'normalize_rule' => 'sometimes|string|in:NUMERIC,ALNUM,UPPER,NONE',
            'example' => 'nullable|string|max:50',
            'checksum_type' => 'sometimes|string|in:LUHN,MOD_N,NONE',
        ]);

        $scheme->update($validated);

        return response()->json($scheme);
    }

    /**
     * Check identifier uniqueness
     */
    public function checkIdentifierUnique(Request $request)
    {
        $user = $request->user();
        
        // Superadmin can check for any tenant via query param
        if ($user->isSuperadmin() && $request->has('tenant_id')) {
            $tenantId = $request->input('tenant_id');
        } else {
            // For non-superadmin, get from middleware context
            $tenantId = $request->input('tenant_id') 
                ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
            
            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant context is required',
                    'error' => 'TENANT_REQUIRED'
                ], 403);
            }
        }

        $validated = $request->validate([
            'scheme_id' => 'required|exists:identifier_schemes,id',
            'norm_value' => 'required|string',
            'scope_entity_id' => 'nullable|integer',
        ]);

        $exists = PersonIdentifier::where('tenant_id', $tenantId)
            ->where('scheme_id', $validated['scheme_id'])
            ->where('norm_value', $validated['norm_value'])
            ->where(function ($query) use ($validated) {
                if ($validated['scope_entity_id']) {
                    $query->where('scope_entity_id', $validated['scope_entity_id']);
                } else {
                    $query->whereNull('scope_entity_id');
                }
            })
            ->exists();

        return response()->json([
            'is_unique' => !$exists,
            'exists' => $exists,
        ]);
    }
}
