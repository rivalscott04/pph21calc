<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IdentifierScheme;
use App\Models\Person;
use App\Models\PersonIdentifier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PersonController extends Controller
{
    /**
     * List persons with pagination
     */
    public function index(Request $request)
    {
        $query = Person::query()
            ->with(['identifiers.scheme']) // Eager load identifiers with scheme to avoid N+1
            ->orderBy('full_name');

        // Search by name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('npwp', 'like', "%{$search}%");
            });
        }

        // Filter by identifier
        if ($request->has('identifier')) {
            $identifier = $request->input('identifier');
            $query->whereHas('identifiers', function ($q) use ($identifier) {
                $q->where('norm_value', $identifier)
                  ->orWhere('raw_value', $identifier);
            });
        }

        $perPage = $request->input('per_page', 15);
        $persons = $query->paginate($perPage);

        return response()->json($persons);
    }

    /**
     * Create person
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'nik' => 'nullable|string|max:16',
            'npwp' => 'nullable|string|max:15',
            'birth_date' => 'nullable|date',
        ]);

        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $person = Person::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'full_name' => $validated['full_name'],
            'nik' => $validated['nik'] ?? null,
            'npwp' => $validated['npwp'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
        ]);

        return response()->json($person->load(['identifiers.scheme']), 201);
    }

    /**
     * Get person by ID
     */
    public function show(Request $request, $id)
    {
        $person = Person::with([
            'identifiers.scheme', // Eager load to avoid N+1
            'employments.orgUnit', // Eager load employments with org unit
            'employments.payrollSubject', // Eager load payroll subject
        ])->findOrFail($id);

        return response()->json($person);
    }

    /**
     * Resolve person by identifier (for search/lookup)
     */
    public function resolve(Request $request)
    {
        $request->validate([
            'q' => 'required|string',
        ]);

        $query = $request->input('q');

        // Try to find by identifier first
        $identifier = PersonIdentifier::with(['person.identifiers.scheme', 'scheme'])
            ->where('norm_value', $query)
            ->orWhere('raw_value', $query)
            ->first();

        if ($identifier) {
            return response()->json([
                'found' => true,
                'person' => $identifier->person->load(['identifiers.scheme', 'employments.orgUnit']),
                'matched_identifier' => [
                    'scheme' => $identifier->scheme->code,
                    'value' => $identifier->raw_value,
                ],
            ]);
        }

        // Fallback to name search
        $person = Person::with(['identifiers.scheme', 'employments.orgUnit'])
            ->where('full_name', 'like', "%{$query}%")
            ->orWhere('nik', $query)
            ->orWhere('npwp', $query)
            ->first();

        if ($person) {
            return response()->json([
                'found' => true,
                'person' => $person,
            ]);
        }

        return response()->json([
            'found' => false,
            'person' => null,
        ], 404);
    }

    /**
     * Add identifier to person
     */
    public function addIdentifier(Request $request, $id)
    {
        $person = Person::findOrFail($id);

        $validated = $request->validate([
            'scheme_id' => 'required|exists:identifier_schemes,id',
            'raw_value' => 'required|string|max:255',
            'scope_entity_id' => 'nullable|integer',
            'scope_org_unit_id' => 'nullable|integer|exists:org_units,id',
            'effective_start' => 'nullable|date',
            'effective_end' => 'nullable|date|after:effective_start',
            'is_primary' => 'sometimes|boolean',
        ]);

        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        // Get scheme to normalize value
        $scheme = IdentifierScheme::findOrFail($validated['scheme_id']);

        // Normalize value based on scheme rules
        $normValue = $this->normalizeIdentifier($validated['raw_value'], $scheme);

        // Check uniqueness
        $exists = PersonIdentifier::where('tenant_id', $tenantId)
            ->where('scheme_id', $validated['scheme_id'])
            ->where('norm_value', $normValue)
            ->where(function ($query) use ($validated) {
                if ($validated['scope_entity_id']) {
                    $query->where('scope_entity_id', $validated['scope_entity_id']);
                } else {
                    $query->whereNull('scope_entity_id');
                }
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Identifier already exists',
                'error' => 'DUPLICATE_IDENTIFIER'
            ], 422);
        }

        // If this is set as primary, unset other primary identifiers for this scheme
        if ($validated['is_primary'] ?? false) {
            PersonIdentifier::where('person_id', $person->id)
                ->where('scheme_id', $validated['scheme_id'])
                ->update(['is_primary' => false]);
        }

        $identifier = PersonIdentifier::create([
            'tenant_id' => $tenantId,
            'person_id' => $person->id,
            'scheme_id' => $validated['scheme_id'],
            'raw_value' => $validated['raw_value'],
            'norm_value' => $normValue,
            'scope_entity_id' => $validated['scope_entity_id'] ?? null,
            'scope_org_unit_id' => $validated['scope_org_unit_id'] ?? null,
            'effective_start' => $validated['effective_start'] ?? null,
            'effective_end' => $validated['effective_end'] ?? null,
            'is_primary' => $validated['is_primary'] ?? false,
        ]);

        return response()->json($identifier->load('scheme'), 201);
    }

    /**
     * Normalize identifier value based on scheme rules
     */
    private function normalizeIdentifier(string $value, IdentifierScheme $scheme): string
    {
        $normalized = $value;

        switch ($scheme->normalize_rule) {
            case 'NUMERIC':
                $normalized = preg_replace('/[^0-9]/', '', $value);
                break;
            case 'ALNUM':
                $normalized = preg_replace('/[^A-Za-z0-9]/', '', $value);
                break;
            case 'UPPER':
                $normalized = strtoupper($value);
                break;
            case 'NONE':
            default:
                // No normalization
                break;
        }

        return $normalized;
    }
}
