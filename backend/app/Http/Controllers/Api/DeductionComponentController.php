<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeductionComponent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeductionComponentController extends Controller
{
    /**
     * List deduction components
     */
    public function index(Request $request)
    {
        $query = DeductionComponent::query()->orderBy('priority')->orderBy('code');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by calculation_type
        if ($request->has('calculation_type')) {
            $query->where('calculation_type', $request->input('calculation_type'));
        }

        // Filter by is_active
        if ($request->has('is_active')) {
            $query->where('is_active', $request->input('is_active') === 'true' || $request->input('is_active') === true);
        }

        // Filter by is_tax_deductible
        if ($request->has('is_tax_deductible')) {
            $query->where('is_tax_deductible', $request->input('is_tax_deductible') === 'true' || $request->input('is_tax_deductible') === true);
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
     * Get deduction component by ID
     */
    public function show(Request $request, $id)
    {
        $component = DeductionComponent::findOrFail($id);

        return response()->json($component);
    }

    /**
     * Create deduction component
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
                Rule::unique('deduction_components')->where('tenant_id', $tenantId),
            ],
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:mandatory,custom',
            'calculation_type' => 'required|string|in:auto,manual,percentage',
            'is_tax_deductible' => 'required|boolean',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $component = DeductionComponent::create([
            'tenant_id' => $tenantId,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'calculation_type' => $validated['calculation_type'],
            'is_tax_deductible' => $validated['is_tax_deductible'],
            'priority' => $validated['priority'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($component, 201);
    }

    /**
     * Update deduction component
     */
    public function update(Request $request, $id)
    {
        $component = DeductionComponent::findOrFail($id);
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('deduction_components')->where('tenant_id', $tenantId)->ignore($component->id),
            ],
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:mandatory,custom',
            'calculation_type' => 'sometimes|string|in:auto,manual,percentage',
            'is_tax_deductible' => 'sometimes|boolean',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $component->update($validated);

        return response()->json($component);
    }

    /**
     * Delete deduction component
     */
    public function destroy(Request $request, $id)
    {
        $component = DeductionComponent::findOrFail($id);
        
        // Check if component is used in deductions
        if ($component->deductions()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete deduction component that is already used in deductions'
            ], 422);
        }

        $component->delete();

        return response()->json(['message' => 'Deduction component deleted successfully']);
    }
}
