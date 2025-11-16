<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComponentController extends Controller
{
    /**
     * List components
     */
    public function index(Request $request)
    {
        $query = Component::query()->orderBy('code');

        // Filter by group
        if ($request->has('group')) {
            $query->where('group', $request->input('group'));
        }

        // Filter by taxable
        if ($request->has('taxable')) {
            $query->where('taxable', $request->input('taxable') === 'true' || $request->input('taxable') === true);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 50);
        $components = $query->paginate($perPage);

        return response()->json($components);
    }

    /**
     * Get component by ID
     */
    public function show(Request $request, $id)
    {
        $component = Component::findOrFail($id);

        return response()->json($component);
    }

    /**
     * Create component
     */
    public function store(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('components')->where('tenant_id', $tenantId),
            ],
            'name' => 'required|string|max:255',
            'group' => 'required|string|in:gaji_pokok,tunjangan,bonus,lembur,natura,lainnya',
            'taxable' => 'required|boolean',
            'is_mandatory' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $component = Component::create([
            'tenant_id' => $tenantId,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'group' => $validated['group'],
            'taxable' => $validated['taxable'],
            'is_mandatory' => $validated['is_mandatory'] ?? false,
            'priority' => $validated['priority'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($component, 201);
    }

    /**
     * Update component
     */
    public function update(Request $request, $id)
    {
        $component = Component::findOrFail($id);
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('components')->where('tenant_id', $tenantId)->ignore($component->id),
            ],
            'name' => 'sometimes|string|max:255',
            'group' => 'sometimes|string|in:gaji_pokok,tunjangan,bonus,lembur,natura,lainnya',
            'taxable' => 'sometimes|boolean',
            'is_mandatory' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $component->update($validated);

        return response()->json($component);
    }
}

