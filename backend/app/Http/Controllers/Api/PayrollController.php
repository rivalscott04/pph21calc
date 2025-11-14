<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\DeductionsManual;
use App\Models\Earning;
use App\Models\Employment;
use App\Models\PayrollCalculation;
use App\Models\Period;
use App\Services\PPh21CalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    /**
     * List earnings
     */
    public function earnings(Request $request)
    {
        $query = Earning::query()
            ->with([
                'employment.person.identifiers.scheme',
                'employment.orgUnit',
                'component',
                'period',
            ])
            ->orderBy('created_at', 'desc');

        // Filter by period
        if ($request->has('period')) {
            $query->where('period_id', $request->input('period'));
        }

        // Filter by employment
        if ($request->has('employment_id')) {
            $query->where('employment_id', $request->input('employment_id'));
        }

        $perPage = $request->input('per_page', 50);
        $earnings = $query->paginate($perPage);

        return response()->json($earnings);
    }

    /**
     * Create or update earnings (bulk)
     */
    public function storeEarnings(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $validated = $request->validate([
            'period_id' => 'required|exists:periods,id',
            'earnings' => 'required|array',
            'earnings.*.employment_id' => 'required|exists:employments,id',
            'earnings.*.component_id' => 'required|exists:components,id',
            'earnings.*.amount' => 'required|numeric|min:0',
            'earnings.*.meta' => 'nullable|array',
        ]);

        $period = Period::findOrFail($validated['period_id']);

        // Verify period belongs to tenant
        if ($period->tenant_id != $tenantId) {
            return response()->json([
                'message' => 'Period does not belong to tenant',
                'error' => 'INVALID_TENANT'
            ], 422);
        }

        // Verify period is not posted
        if ($period->status === 'posted') {
            return response()->json([
                'message' => 'Cannot modify earnings for posted period',
                'error' => 'PERIOD_POSTED'
            ], 422);
        }

        $created = [];
        $updated = [];

        DB::transaction(function () use ($validated, $tenantId, &$created, &$updated) {
            foreach ($validated['earnings'] as $earningData) {
                // Verify employment belongs to tenant
                $employment = Employment::findOrFail($earningData['employment_id']);
                if ($employment->tenant_id != $tenantId) {
                    continue;
                }

                // Check if earning already exists (same period, employment, component)
                $existing = Earning::where('tenant_id', $tenantId)
                    ->where('period_id', $validated['period_id'])
                    ->where('employment_id', $earningData['employment_id'])
                    ->where('component_id', $earningData['component_id'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'amount' => $earningData['amount'],
                        'meta' => $earningData['meta'] ?? null,
                    ]);
                    $updated[] = $existing;
                } else {
                    $earning = Earning::create([
                        'tenant_id' => $tenantId,
                        'employment_id' => $earningData['employment_id'],
                        'period_id' => $validated['period_id'],
                        'component_id' => $earningData['component_id'],
                        'amount' => $earningData['amount'],
                        'meta' => $earningData['meta'] ?? null,
                    ]);
                    $created[] = $earning;
                }
            }
        });

        return response()->json([
            'message' => 'Earnings saved successfully',
            'created' => count($created),
            'updated' => count($updated),
        ], 201);
    }

    /**
     * List deductions
     */
    public function deductions(Request $request)
    {
        $query = DeductionsManual::query()
            ->with([
                'employment.person.identifiers.scheme',
                'employment.orgUnit',
                'period',
            ])
            ->orderBy('created_at', 'desc');

        // Filter by period
        if ($request->has('period')) {
            $query->where('period_id', $request->input('period'));
        }

        // Filter by employment
        if ($request->has('employment_id')) {
            $query->where('employment_id', $request->input('employment_id'));
        }

        $perPage = $request->input('per_page', 50);
        $deductions = $query->paginate($perPage);

        return response()->json($deductions);
    }

    /**
     * Create or update deductions (bulk)
     */
    public function storeDeductions(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $validated = $request->validate([
            'period_id' => 'required|exists:periods,id',
            'deductions' => 'required|array',
            'deductions.*.employment_id' => 'required|exists:employments,id',
            'deductions.*.type' => 'required|string|in:iuran_pensiun,zakat,lainnya',
            'deductions.*.amount' => 'required|numeric|min:0',
        ]);

        $period = Period::findOrFail($validated['period_id']);

        // Verify period belongs to tenant
        if ($period->tenant_id != $tenantId) {
            return response()->json([
                'message' => 'Period does not belong to tenant',
                'error' => 'INVALID_TENANT'
            ], 422);
        }

        // Verify period is not posted
        if ($period->status === 'posted') {
            return response()->json([
                'message' => 'Cannot modify deductions for posted period',
                'error' => 'PERIOD_POSTED'
            ], 422);
        }

        $created = [];
        $updated = [];

        DB::transaction(function () use ($validated, $tenantId, &$created, &$updated) {
            foreach ($validated['deductions'] as $deductionData) {
                // Verify employment belongs to tenant
                $employment = Employment::findOrFail($deductionData['employment_id']);
                if ($employment->tenant_id != $tenantId) {
                    continue;
                }

                // Check if deduction already exists (same period, employment, type)
                $existing = DeductionsManual::where('tenant_id', $tenantId)
                    ->where('period_id', $validated['period_id'])
                    ->where('employment_id', $deductionData['employment_id'])
                    ->where('type', $deductionData['type'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'amount' => $deductionData['amount'],
                    ]);
                    $updated[] = $existing;
                } else {
                    $deduction = DeductionsManual::create([
                        'tenant_id' => $tenantId,
                        'employment_id' => $deductionData['employment_id'],
                        'period_id' => $validated['period_id'],
                        'type' => $deductionData['type'],
                        'amount' => $deductionData['amount'],
                    ]);
                    $created[] = $deduction;
                }
            }
        });

        return response()->json([
            'message' => 'Deductions saved successfully',
            'created' => count($created),
            'updated' => count($updated),
        ], 201);
    }

    /**
     * Preview payroll calculations (without saving)
     */
    public function preview(Request $request, $periodId)
    {
        $period = Period::with(['earnings.component', 'deductions'])->findOrFail($periodId);
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        // Verify period belongs to tenant
        if ($period->tenant_id != $tenantId) {
            return response()->json([
                'message' => 'Period does not belong to tenant',
                'error' => 'INVALID_TENANT'
            ], 403);
        }

        // Group earnings and deductions by employment
        $employmentIds = $period->earnings->pluck('employment_id')
            ->merge($period->deductions->pluck('employment_id'))
            ->unique();

        $calculator = new PPh21CalculatorService();
        $previews = [];

        foreach ($employmentIds as $employmentId) {
            $employment = Employment::with([
                'person',
                'payrollSubject',
            ])->find($employmentId);

            if (!$employment || $employment->tenant_id != $tenantId) {
                continue;
            }

            // Get earnings and deductions for this employment
            $earnings = $period->earnings->where('employment_id', $employmentId);
            $deductions = $period->deductions->where('employment_id', $employmentId);

            try {
                // Calculate using service
                $calculation = $calculator->calculate($employment, $period, $earnings, $deductions);

                $previews[] = [
                    'employment_id' => $employmentId,
                    'person_name' => $employment->person->full_name,
                    'bruto' => $calculation['bruto'],
                    'biaya_jabatan' => $calculation['biaya_jabatan'],
                    'iuran_pensiun' => $calculation['iuran_pensiun'],
                    'zakat' => $calculation['zakat'],
                    'neto_masa' => $calculation['neto_masa'],
                    'ptkp_yearly' => $calculation['ptkp_yearly'],
                    'pkp_annualized' => $calculation['pkp_annualized'],
                    'pph21_masa' => $calculation['pph21_masa'],
                    'pph21_ytd' => $calculation['pph21_ytd'],
                    'pph21_settlement_dec' => $calculation['pph21_settlement_dec'],
                ];
            } catch (\Exception $e) {
                // Skip if calculation fails (e.g., no payroll subject)
                continue;
            }
        }

        return response()->json([
            'period_id' => $periodId,
            'period' => $period->year . '-' . str_pad($period->month, 2, '0', STR_PAD_LEFT),
            'previews' => $previews,
        ]);
    }

    /**
     * Commit payroll calculations (save to database)
     */
    public function commit(Request $request, $periodId)
    {
        $period = Period::with(['earnings.component', 'deductions'])->findOrFail($periodId);
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        // Verify period belongs to tenant
        if ($period->tenant_id != $tenantId) {
            return response()->json([
                'message' => 'Period does not belong to tenant',
                'error' => 'INVALID_TENANT'
            ], 403);
        }

        // Verify period status
        if ($period->status !== 'approved') {
            return response()->json([
                'message' => 'Period must be approved before committing',
                'error' => 'INVALID_STATUS',
                'current_status' => $period->status,
            ], 422);
        }
        $calculator = new PPh21CalculatorService();

        // Group earnings and deductions by employment
        $employmentIds = $period->earnings->pluck('employment_id')
            ->merge($period->deductions->pluck('employment_id'))
            ->unique();

        $committed = 0;

        DB::transaction(function () use ($period, $tenantId, $calculator, $employmentIds, &$committed) {
            foreach ($employmentIds as $employmentId) {
                $employment = Employment::with(['payrollSubject'])->find($employmentId);

                if (!$employment || $employment->tenant_id != $tenantId) {
                    continue;
                }

                // Get earnings and deductions for this employment
                $earnings = $period->earnings->where('employment_id', $employmentId);
                $deductions = $period->deductions->where('employment_id', $employmentId);

                try {
                    // Calculate using service
                    $calculation = $calculator->calculate($employment, $period, $earnings, $deductions);

                    PayrollCalculation::updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'employment_id' => $employmentId,
                            'period_id' => $period->id,
                        ],
                        $calculation
                    );

                    $committed++;
                } catch (\Exception $e) {
                    // Skip if calculation fails
                    continue;
                }
            }
        });

        // Update period status to posted
        $period->update(['status' => 'posted']);

        return response()->json([
            'message' => 'Payroll calculations committed successfully',
            'period_id' => $periodId,
            'calculations_count' => $committed,
        ]);
    }

    /**
     * Get payroll summary for period
     */
    public function summary(Request $request, $periodId)
    {
        $period = Period::with([
            'payrollCalculations.employment.person',
            'payrollCalculations.employment.orgUnit',
        ])->findOrFail($periodId);

        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        // Verify period belongs to tenant
        if ($period->tenant_id != $tenantId) {
            return response()->json([
                'message' => 'Period does not belong to tenant',
                'error' => 'INVALID_TENANT'
            ], 403);
        }

        $calculations = $period->payrollCalculations;

        $summary = [
            'period_id' => $periodId,
            'period' => $period->year . '-' . str_pad($period->month, 2, '0', STR_PAD_LEFT),
            'status' => $period->status,
            'total_employees' => $calculations->count(),
            'total_bruto' => $calculations->sum('bruto'),
            'total_neto' => $calculations->sum('neto_masa'),
            'total_pph21' => $calculations->sum('pph21_masa'),
            'calculations' => $calculations->map(function ($calc) {
                return [
                    'employment_id' => $calc->employment_id,
                    'person_name' => $calc->employment->person->full_name ?? 'N/A',
                    'org_unit' => $calc->employment->orgUnit->name ?? 'N/A',
                    'bruto' => $calc->bruto,
                    'neto_masa' => $calc->neto_masa,
                    'pph21_masa' => $calc->pph21_masa,
                ];
            }),
        ];

        return response()->json($summary);
    }

    /**
     * Get payroll slip for specific employment
     */
    public function slip(Request $request, $periodId, $employmentId)
    {
        $period = Period::findOrFail($periodId);
        $employment = Employment::with([
            'person',
            'orgUnit',
            'payrollSubject',
        ])->findOrFail($employmentId);

        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        // Verify period and employment belong to tenant
        if ($period->tenant_id != $tenantId || $employment->tenant_id != $tenantId) {
            return response()->json([
                'message' => 'Period or employment does not belong to tenant',
                'error' => 'INVALID_TENANT'
            ], 403);
        }

        $calculation = PayrollCalculation::where('period_id', $periodId)
            ->where('employment_id', $employmentId)
            ->first();

        if (!$calculation) {
            return response()->json([
                'message' => 'Payroll calculation not found',
                'error' => 'NOT_FOUND'
            ], 404);
        }

        // Get earnings and deductions
        $earnings = Earning::with('component')
            ->where('period_id', $periodId)
            ->where('employment_id', $employmentId)
            ->get();

        $deductions = DeductionsManual::where('period_id', $periodId)
            ->where('employment_id', $employmentId)
            ->get();

        $slip = [
            'period' => $period->year . '-' . str_pad($period->month, 2, '0', STR_PAD_LEFT),
            'person' => [
                'name' => $employment->person->full_name,
                'nik' => $employment->person->nik,
                'npwp' => $employment->person->npwp,
            ],
            'employment' => [
                'org_unit' => $employment->orgUnit->name ?? 'N/A',
                'type' => $employment->employment_type,
            ],
            'earnings' => $earnings->map(function ($earning) {
                return [
                    'component' => $earning->component->name,
                    'amount' => $earning->amount,
                ];
            }),
            'deductions' => $deductions->map(function ($deduction) {
                return [
                    'type' => $deduction->type,
                    'amount' => $deduction->amount,
                ];
            }),
            'calculation' => [
                'bruto' => $calculation->bruto,
                'biaya_jabatan' => $calculation->biaya_jabatan,
                'iuran_pensiun' => $calculation->iuran_pensiun,
                'zakat' => $calculation->zakat,
                'neto_masa' => $calculation->neto_masa,
                'pph21_masa' => $calculation->pph21_masa,
            ],
        ];

        return response()->json($slip);
    }
}
