<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employment;
use App\Models\PayrollSubject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmploymentController extends Controller
{
    /**
     * List employments
     */
    public function index(Request $request)
    {
        $query = Employment::query()
            ->with([
                'person.identifiers.scheme', // Eager load person with identifiers
                'orgUnit', // Eager load org unit
                'payrollSubject', // Eager load active payroll subject
            ])
            ->orderBy('start_date', 'desc');

        // Filter by person
        if ($request->has('person_id')) {
            $query->where('person_id', $request->input('person_id'));
        }

        // Filter by org unit
        if ($request->has('org_unit_id')) {
            $query->where('org_unit_id', $request->input('org_unit_id'));
        }

        // Filter by employment type
        if ($request->has('employment_type')) {
            $query->where('employment_type', $request->input('employment_type'));
        }

        // Filter by active (no end_date or end_date in future)
        if ($request->has('active')) {
            if ($request->boolean('active')) {
                $query->where(function ($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
                });
            } else {
                $query->whereNotNull('end_date')
                      ->where('end_date', '<', now());
            }
        }

        // Filter by primary payroll
        if ($request->has('primary_payroll')) {
            $query->where('primary_payroll', $request->boolean('primary_payroll'));
        }

        $perPage = $request->input('per_page', 15);
        $employments = $query->paginate($perPage);

        return response()->json($employments);
    }

    /**
     * Create employment
     */
    public function store(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $validated = $request->validate([
            'person_id' => 'required|exists:persons,id',
            'org_unit_id' => 'required|exists:org_units,id',
            'employment_type' => 'required|string|in:tetap,tidak_tetap,harian,tenaga_ahli',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'primary_payroll' => 'sometimes|boolean',
        ]);

        // Verify person and org unit belong to same tenant
        $person = \App\Models\Person::findOrFail($validated['person_id']);
        $orgUnit = \App\Models\OrgUnit::findOrFail($validated['org_unit_id']);

        if ($person->tenant_id != $tenantId || $orgUnit->tenant_id != $tenantId) {
            return response()->json([
                'message' => 'Person and org unit must belong to the same tenant',
                'error' => 'INVALID_TENANT'
            ], 422);
        }

        // If this is set as primary, unset other primary employments for this person
        if ($validated['primary_payroll'] ?? false) {
            Employment::where('person_id', $validated['person_id'])
                ->update(['primary_payroll' => false]);
        }

        $employment = Employment::create([
            'tenant_id' => $tenantId,
            'person_id' => $validated['person_id'],
            'org_unit_id' => $validated['org_unit_id'],
            'employment_type' => $validated['employment_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'primary_payroll' => $validated['primary_payroll'] ?? false,
        ]);

        return response()->json($employment->load(['person.identifiers.scheme', 'orgUnit', 'payrollSubject']), 201);
    }

    /**
     * Get employment by ID
     */
    public function show(Request $request, $id)
    {
        $employment = Employment::with([
            'person.identifiers.scheme',
            'orgUnit.parent',
            'payrollSubject',
            'payrollSubjects', // All payroll subjects (not just active)
            'earnings.component',
            'deductions',
        ])->findOrFail($id);

        return response()->json($employment);
    }

    /**
     * Update employment
     */
    public function update(Request $request, $id)
    {
        $employment = Employment::findOrFail($id);
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $validated = $request->validate([
            'org_unit_id' => 'sometimes|exists:org_units,id',
            'employment_type' => 'sometimes|string|in:tetap,tidak_tetap,harian,tenaga_ahli',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after:start_date',
            'primary_payroll' => 'sometimes|boolean',
        ]);

        // Verify org unit belongs to same tenant
        if (isset($validated['org_unit_id'])) {
            $orgUnit = \App\Models\OrgUnit::findOrFail($validated['org_unit_id']);
            if ($orgUnit->tenant_id != $tenantId) {
                return response()->json([
                    'message' => 'Org unit must belong to the same tenant',
                    'error' => 'INVALID_TENANT'
                ], 422);
            }
        }

        // If this is set as primary, unset other primary employments for this person
        if (isset($validated['primary_payroll']) && $validated['primary_payroll']) {
            Employment::where('person_id', $employment->person_id)
                ->where('id', '!=', $employment->id)
                ->update(['primary_payroll' => false]);
        }

        $employment->update($validated);

        return response()->json($employment->load(['person.identifiers.scheme', 'orgUnit', 'payrollSubject']));
    }

    /**
     * List payroll subjects
     */
    public function payrollSubjects(Request $request)
    {
        $query = PayrollSubject::query()
            ->with([
                'employment.person.identifiers.scheme', // Eager load employment with person
                'employment.orgUnit', // Eager load org unit
            ])
            ->orderBy('created_at', 'desc');

        // Filter by employment
        if ($request->has('employment_id')) {
            $query->where('employment_id', $request->input('employment_id'));
        }

        // Filter by active
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $perPage = $request->input('per_page', 15);
        $subjects = $query->paginate($perPage);

        return response()->json($subjects);
    }

    /**
     * Create payroll subject
     */
    public function createPayrollSubject(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $validated = $request->validate([
            'employment_id' => 'required|exists:employments,id',
            'ptkp_code' => 'required|string|max:10',
            'has_npwp' => 'sometimes|boolean',
            'tax_profile_json' => 'nullable|array',
            'active' => 'sometimes|boolean',
        ]);

        // Verify employment belongs to same tenant
        $employment = Employment::findOrFail($validated['employment_id']);
        if ($employment->tenant_id != $tenantId) {
            return response()->json([
                'message' => 'Employment must belong to the same tenant',
                'error' => 'INVALID_TENANT'
            ], 422);
        }

        // If this is set as active, deactivate other active payroll subjects for this employment
        if ($validated['active'] ?? true) {
            PayrollSubject::where('employment_id', $validated['employment_id'])
                ->where('active', true)
                ->update(['active' => false]);
        }

        $subject = PayrollSubject::create([
            'tenant_id' => $tenantId,
            'employment_id' => $validated['employment_id'],
            'ptkp_code' => $validated['ptkp_code'],
            'has_npwp' => $validated['has_npwp'] ?? false,
            'tax_profile_json' => $validated['tax_profile_json'] ?? null,
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json($subject->load(['employment.person.identifiers.scheme', 'employment.orgUnit']), 201);
    }

    /**
     * Get payroll subject by ID
     */
    public function showPayrollSubject(Request $request, $id)
    {
        $subject = PayrollSubject::with([
            'employment.person.identifiers.scheme',
            'employment.orgUnit',
        ])->findOrFail($id);

        return response()->json($subject);
    }

    /**
     * Update payroll subject
     */
    public function updatePayrollSubject(Request $request, $id)
    {
        $subject = PayrollSubject::findOrFail($id);

        $validated = $request->validate([
            'ptkp_code' => 'sometimes|string|max:10',
            'has_npwp' => 'sometimes|boolean',
            'tax_profile_json' => 'nullable|array',
            'active' => 'sometimes|boolean',
        ]);

        // If this is set as active, deactivate other active payroll subjects for this employment
        if (isset($validated['active']) && $validated['active']) {
            PayrollSubject::where('employment_id', $subject->employment_id)
                ->where('id', '!=', $subject->id)
                ->where('active', true)
                ->update(['active' => false]);
        }

        $subject->update($validated);

        return response()->json($subject->load(['employment.person.identifiers.scheme', 'employment.orgUnit']));
    }
}
