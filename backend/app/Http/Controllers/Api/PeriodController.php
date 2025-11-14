<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeriodController extends Controller
{
    /**
     * List periods
     */
    public function index(Request $request)
    {
        $query = Period::query()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        // Filter by year
        if ($request->has('year')) {
            $query->where('year', $request->input('year'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by year and month
        if ($request->has('year') && $request->has('month')) {
            $query->where('year', $request->input('year'))
                  ->where('month', $request->input('month'));
        }

        $perPage = $request->input('per_page', 15);
        $periods = $query->paginate($perPage);

        return response()->json($periods);
    }

    /**
     * Create period
     */
    public function store(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'status' => 'nullable|string|in:draft,reviewed,approved,posted',
        ], [
            'year.required' => 'Year is required',
            'month.required' => 'Month is required',
            'month.min' => 'Month must be between 1 and 12',
            'month.max' => 'Month must be between 1 and 12',
        ]);

        // Check if period already exists
        $exists = Period::where('tenant_id', $tenantId)
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Period already exists',
                'error' => 'DUPLICATE_PERIOD'
            ], 422);
        }

        $period = Period::create([
            'tenant_id' => $tenantId,
            'year' => $validated['year'],
            'month' => $validated['month'],
            'status' => $validated['status'] ?? 'draft',
        ]);

        return response()->json($period, 201);
    }

    /**
     * Get period by ID
     */
    public function show(Request $request, $id)
    {
        $period = Period::with([
            'earnings.employment.person',
            'earnings.component',
            'deductions.employment.person',
            'payrollCalculations.employment.person',
        ])->findOrFail($id);

        return response()->json($period);
    }

    /**
     * Update period status
     */
    public function updateStatus(Request $request, $id)
    {
        $period = Period::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,reviewed,approved,posted',
        ]);

        // Validate status transition
        $allowedTransitions = [
            'draft' => ['reviewed'],
            'reviewed' => ['draft', 'approved'],
            'approved' => ['reviewed', 'posted'],
            'posted' => [], // Cannot change from posted
        ];

        if ($period->status === 'posted') {
            return response()->json([
                'message' => 'Cannot change status of posted period',
                'error' => 'INVALID_STATUS_TRANSITION'
            ], 422);
        }

        if (!in_array($validated['status'], $allowedTransitions[$period->status] ?? [])) {
            return response()->json([
                'message' => 'Invalid status transition',
                'error' => 'INVALID_STATUS_TRANSITION',
                'current_status' => $period->status,
                'allowed_transitions' => $allowedTransitions[$period->status] ?? [],
            ], 422);
        }

        $period->update(['status' => $validated['status']]);

        return response()->json($period);
    }
}
