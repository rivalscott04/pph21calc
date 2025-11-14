<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrgUnitController extends Controller
{
    /**
     * List org units (tree structure)
     */
    public function index(Request $request)
    {
        $query = OrgUnit::query()
            ->with(['parent', 'children']) // Eager load parent and children to avoid N+1
            ->orderBy('code');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by parent
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->input('parent_id'));
        } else {
            // Default: show root units (no parent)
            if (!$request->has('all')) {
                $query->whereNull('parent_id');
            }
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
        $orgUnits = $query->paginate($perPage);

        return response()->json($orgUnits);
    }

    /**
     * Get org unit tree (recursive)
     */
    public function tree(Request $request)
    {
        $query = OrgUnit::query()
            ->whereNull('parent_id')
            ->orderBy('code');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $roots = $query->get();

        // Eager load all children recursively
        $this->loadChildrenRecursive($roots);

        // Build tree recursively
        $tree = $roots->map(function ($unit) {
            return $this->buildTree($unit);
        });

        return response()->json($tree);
    }

    /**
     * Load children recursively to avoid N+1 queries
     */
    private function loadChildrenRecursive($units)
    {
        if ($units->isEmpty()) {
            return;
        }

        $ids = $units->pluck('id');
        $children = OrgUnit::whereIn('parent_id', $ids)
            ->orderBy('code')
            ->get();

        if ($children->isNotEmpty()) {
            // Group children by parent_id
            $childrenByParent = $children->groupBy('parent_id');

            // Attach children to their parents
            foreach ($units as $unit) {
                $unit->setRelation('children', $childrenByParent->get($unit->id, collect()));
            }

            // Recursively load children of children
            $this->loadChildrenRecursive($children);
        } else {
            // No more children, set empty collection
            foreach ($units as $unit) {
                if (!$unit->relationLoaded('children')) {
                    $unit->setRelation('children', collect());
                }
            }
        }
    }

    /**
     * Create org unit
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
                Rule::unique('org_units')->where('tenant_id', $tenantId),
            ],
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
            'parent_id' => 'nullable|exists:org_units,id',
        ]);

        // Verify parent belongs to same tenant
        if (isset($validated['parent_id']) && $validated['parent_id']) {
            $parent = OrgUnit::findOrFail($validated['parent_id']);
            if ($parent->tenant_id != $tenantId) {
                return response()->json([
                    'message' => 'Parent org unit must belong to the same tenant',
                    'error' => 'INVALID_PARENT'
                ], 422);
            }
        }

        $orgUnit = OrgUnit::create([
            'tenant_id' => $tenantId,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        return response()->json($orgUnit->load(['parent', 'children']), 201);
    }

    /**
     * Get org unit by ID
     */
    public function show(Request $request, $id)
    {
        $orgUnit = OrgUnit::with([
            'parent',
            'children',
            'employments.person', // Eager load employments with person
        ])->findOrFail($id);

        return response()->json($orgUnit);
    }

    /**
     * Update org unit
     */
    public function update(Request $request, $id)
    {
        $orgUnit = OrgUnit::findOrFail($id);
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('org_units')->where('tenant_id', $tenantId)->ignore($orgUnit->id),
            ],
            'name' => 'sometimes|string|max:255',
            'type' => 'nullable|string|max:50',
            'parent_id' => [
                'nullable',
                'exists:org_units,id',
                function ($attribute, $value, $fail) use ($orgUnit, $tenantId) {
                    // Prevent circular reference
                    if ($value == $orgUnit->id) {
                        $fail('Org unit cannot be its own parent.');
                    }
                    // Verify parent belongs to same tenant
                    if ($value) {
                        $parent = OrgUnit::find($value);
                        if ($parent && $parent->tenant_id != $tenantId) {
                            $fail('Parent org unit must belong to the same tenant.');
                        }
                    }
                },
            ],
        ]);

        // Prevent setting parent to a descendant
        if ($validated['parent_id'] ?? null) {
            $descendants = $this->getDescendants($orgUnit);
            if ($descendants->contains('id', $validated['parent_id'])) {
                return response()->json([
                    'message' => 'Cannot set parent to a descendant',
                    'error' => 'CIRCULAR_REFERENCE'
                ], 422);
            }
        }

        $orgUnit->update($validated);

        return response()->json($orgUnit->load(['parent', 'children']));
    }

    /**
     * Build tree structure recursively
     */
    private function buildTree(OrgUnit $unit): array
    {
        $data = [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'type' => $unit->type,
            'parent_id' => $unit->parent_id,
        ];

        if ($unit->relationLoaded('children') && $unit->children->isNotEmpty()) {
            $data['children'] = $unit->children->map(function ($child) {
                return $this->buildTree($child);
            })->toArray();
        }

        return $data;
    }

    /**
     * Get all descendants of an org unit
     */
    private function getDescendants(OrgUnit $unit)
    {
        $descendants = collect();
        $children = $unit->children;

        foreach ($children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->getDescendants($child));
        }

        return $descendants;
    }
}
