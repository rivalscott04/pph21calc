<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalculationHistory;
use App\Models\Employment;
use App\Services\PPh21CalculatorService;
use Illuminate\Http\Request;

class CalculatorController extends Controller
{
    /**
     * Calculate PPh21 (standalone calculator)
     * Supports both old format (bruto, biaya_jabatan, iuran_pensiun, zakat) and new format (earnings, deductions)
     */
    public function calculatePph21(Request $request)
    {
        // Normalize empty strings to null for optional numeric fields before validation
        $input = $request->all();
        if (isset($input['zakat']) && $input['zakat'] === '') {
            $input['zakat'] = null;
        }
        if (isset($input['biaya_jabatan']) && $input['biaya_jabatan'] === '') {
            $input['biaya_jabatan'] = null;
        }
        if (isset($input['iuran_pensiun']) && $input['iuran_pensiun'] === '') {
            $input['iuran_pensiun'] = null;
        }
        $request->merge($input);

        // Check if using new format (earnings and deductions arrays)
        $isNewFormat = $request->has('earnings') || $request->has('deductions');

        if ($isNewFormat) {
            // New format: earnings and deductions arrays
            $validated = $request->validate([
                'ptkp_code' => 'required|string|in:TK0,TK1,TK2,TK3,K0,K1,K2,K3',
                'earnings' => 'required|array|min:1',
                'earnings.*.component_id' => 'required|exists:components,id',
                'earnings.*.amount' => 'required|numeric|min:0',
                'deductions' => 'nullable|array',
                'deductions.*.deduction_component_id' => 'required|exists:deduction_components,id',
                'deductions.*.amount' => 'required|numeric|min:0',
                'month' => 'nullable|integer|min:1|max:12',
                'has_npwp' => 'nullable|boolean',
            ]);

            $calculator = new PPh21CalculatorService();

            try {
                // Load components and deduction components
                $componentIds = collect($validated['earnings'])->pluck('component_id')->unique();
                $components = \App\Models\Component::whereIn('id', $componentIds)->get()->keyBy('id');
                
                $deductionComponentIds = collect($validated['deductions'] ?? [])->pluck('deduction_component_id')->unique();
                $deductionComponents = \App\Models\DeductionComponent::whereIn('id', $deductionComponentIds)->get()->keyBy('id');

                // Calculate bruto from taxable earnings
                $bruto = 0;
                foreach ($validated['earnings'] as $earning) {
                    $component = $components->get($earning['component_id']);
                    if ($component && $component->taxable) {
                        $bruto += (float) $earning['amount'];
                    }
                }

                // Calculate deductions from deduction components
                $biayaJabatan = null;
                $iuranPensiun = null;
                $zakat = 0.0;
                $otherTaxDeductibleDeductions = 0.0;

                foreach ($validated['deductions'] ?? [] as $deduction) {
                    $deductionComponent = $deductionComponents->get($deduction['deduction_component_id']);
                    if (!$deductionComponent) {
                        continue;
                    }

                    $amount = (float) $deduction['amount'];

                    // Identify special deductions
                    $isBiayaJabatan = $deductionComponent->code === 'biaya_jabatan' 
                        || (str_contains(strtolower($deductionComponent->name), 'biaya jabatan')
                            && $deductionComponent->calculation_type === 'auto');
                    
                    $isIuranPensiun = $deductionComponent->code === 'iuran_pensiun'
                        || (str_contains(strtolower($deductionComponent->name), 'iuran pensiun')
                        || ($deductionComponent->type === 'mandatory' && $deductionComponent->calculation_type === 'auto'));
                    
                    $isZakat = $deductionComponent->code === 'zakat'
                        || str_contains(strtolower($deductionComponent->name), 'zakat');

                    if ($isBiayaJabatan) {
                        $biayaJabatan = $amount;
                    } elseif ($isIuranPensiun) {
                        $iuranPensiun = $amount;
                    } elseif ($isZakat) {
                        $zakat = $amount;
                    } elseif ($deductionComponent->is_tax_deductible) {
                        // Other tax-deductible deductions
                        $otherTaxDeductibleDeductions += $amount;
                    }
                }

                // Auto-calculate biaya_jabatan and iuran_pensiun if not provided and calculation_type is auto
                if ($biayaJabatan === null) {
                    $biayaJabatanComponent = $deductionComponents->first(function ($dc) {
                        return $dc->code === 'biaya_jabatan' 
                            || (str_contains(strtolower($dc->name), 'biaya jabatan') && $dc->calculation_type === 'auto');
                    });
                    if ($biayaJabatanComponent) {
                        // Will be calculated by calculateStandalone if null
                        $biayaJabatan = null;
                    }
                }

                if ($iuranPensiun === null) {
                    $iuranPensiunComponent = $deductionComponents->first(function ($dc) {
                        return $dc->code === 'iuran_pensiun'
                            || (str_contains(strtolower($dc->name), 'iuran pensiun') && $dc->calculation_type === 'auto');
                    });
                    if ($iuranPensiunComponent) {
                        // Will be calculated by calculateStandalone if null
                        $iuranPensiun = null;
                    }
                }

                // Ensure zakat is never negative
                $zakat = max(0.0, $zakat);

                $result = $calculator->calculateStandalone(
                    ptkpCode: $validated['ptkp_code'],
                    bruto: $bruto,
                    biayaJabatan: $biayaJabatan,
                    iuranPensiun: $iuranPensiun,
                    zakat: $zakat,
                    month: $validated['month'] ?? 11,
                    hasNpwp: $validated['has_npwp'] ?? true
                );

                return response()->json($result);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Calculation failed',
                    'error' => $e->getMessage(),
                ], 422);
            }
        } else {
            // Old format: bruto, biaya_jabatan, iuran_pensiun, zakat (backward compatibility)
            $validated = $request->validate([
                'ptkp_code' => 'required|string|in:TK0,TK1,TK2,TK3,K0,K1,K2,K3',
                'bruto' => 'required|numeric|min:0',
                'biaya_jabatan' => 'nullable|numeric|min:0',
                'iuran_pensiun' => 'nullable|numeric|min:0',
                'zakat' => 'nullable|numeric|min:0',
                'month' => 'nullable|integer|min:1|max:12',
                'has_npwp' => 'nullable|boolean',
            ]);

            $calculator = new PPh21CalculatorService();

            try {
                // Defensive casting: Ensure all numeric values are properly cast to float
                // This handles cases where values come as strings (e.g., "1.500.000" from formatted inputs)
                $bruto = (float) $validated['bruto'];
                $biayaJabatan = isset($validated['biaya_jabatan']) && $validated['biaya_jabatan'] !== null ? (float) $validated['biaya_jabatan'] : null;
                $iuranPensiun = isset($validated['iuran_pensiun']) && $validated['iuran_pensiun'] !== null ? (float) $validated['iuran_pensiun'] : null;
                
                // Safe zakat casting: treat null, empty, or missing as 0
                $zakat = 0.0;
                if (isset($validated['zakat']) && $validated['zakat'] !== null && $validated['zakat'] !== '') {
                    $zakat = (float) $validated['zakat'];
                }
                
                // CRITICAL: Ensure zakat is never negative - default to 0 if invalid
                $zakat = max(0.0, $zakat);

                $result = $calculator->calculateStandalone(
                    ptkpCode: $validated['ptkp_code'],
                    bruto: $bruto,
                    biayaJabatan: $biayaJabatan,
                    iuranPensiun: $iuranPensiun,
                    zakat: $zakat,
                    month: $validated['month'] ?? 11,
                    hasNpwp: $validated['has_npwp'] ?? true
                );

                return response()->json($result);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Calculation failed',
                    'error' => $e->getMessage(),
                ], 422);
            }
        }
    }

    /**
     * Calculate PPh21 for multiple employees (batch)
     */
    public function calculateBatch(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        // Check if using new format (earnings and deductions arrays)
        $isNewFormat = isset($request->input('calculations')[0]['earnings']) || isset($request->input('calculations')[0]['deductions']);

        if ($isNewFormat) {
            // New format: earnings and deductions arrays
            $validated = $request->validate([
                'calculations' => 'required|array|min:1',
                'calculations.*.employment_id' => 'required|exists:employments,id',
                'calculations.*.earnings' => 'required|array|min:1',
                'calculations.*.earnings.*.component_id' => 'required|exists:components,id',
                'calculations.*.earnings.*.amount' => 'required|numeric|min:0',
                'calculations.*.deductions' => 'nullable|array',
                'calculations.*.deductions.*.deduction_component_id' => 'required|exists:deduction_components,id',
                'calculations.*.deductions.*.amount' => 'required|numeric|min:0',
                'month' => 'nullable|integer|min:1|max:12',
            ]);
        } else {
            // Old format: bruto, biaya_jabatan, iuran_pensiun, zakat (backward compatibility)
            // Normalize empty strings to null for optional numeric fields before validation
            $input = $request->all();
            if (isset($input['calculations']) && is_array($input['calculations'])) {
                foreach ($input['calculations'] as $key => $calc) {
                    if (isset($calc['zakat']) && $calc['zakat'] === '') {
                        $input['calculations'][$key]['zakat'] = null;
                    }
                    if (isset($calc['biaya_jabatan']) && $calc['biaya_jabatan'] === '') {
                        $input['calculations'][$key]['biaya_jabatan'] = null;
                    }
                    if (isset($calc['iuran_pensiun']) && $calc['iuran_pensiun'] === '') {
                        $input['calculations'][$key]['iuran_pensiun'] = null;
                    }
                }
            }
            $request->merge($input);

            $validated = $request->validate([
                'calculations' => 'required|array|min:1',
                'calculations.*.employment_id' => 'required|exists:employments,id',
                'calculations.*.bruto' => 'required|numeric|min:0',
                'calculations.*.biaya_jabatan' => 'nullable|numeric|min:0',
                'calculations.*.iuran_pensiun' => 'nullable|numeric|min:0',
                'calculations.*.zakat' => 'nullable|numeric|min:0',
                'month' => 'nullable|integer|min:1|max:12',
            ]);
        }

        $month = $validated['month'] ?? 11;
        $calculator = new PPh21CalculatorService();
        $results = [];

        foreach ($validated['calculations'] as $calcData) {
            $employment = Employment::with(['payrollSubject', 'person'])->findOrFail($calcData['employment_id']);

            // Verify employment belongs to tenant
            if ($employment->tenant_id != $tenantId) {
                $results[] = [
                    'employment_id' => $calcData['employment_id'],
                    'error' => 'Employment does not belong to tenant',
                ];
                continue;
            }

            // Get PTKP and NPWP from payroll subject if available
            $payrollSubject = $employment->payrollSubject;
            $ptkpCode = $payrollSubject?->ptkp_code ?? 'TK0';
            $hasNpwp = $payrollSubject?->has_npwp ?? true;

            try {
                if ($isNewFormat) {
                    // New format: process earnings and deductions arrays
                    // Load components and deduction components
                    $componentIds = collect($calcData['earnings'])->pluck('component_id')->unique();
                    $components = \App\Models\Component::whereIn('id', $componentIds)->get()->keyBy('id');
                    
                    $deductionComponentIds = collect($calcData['deductions'] ?? [])->pluck('deduction_component_id')->unique();
                    $deductionComponents = \App\Models\DeductionComponent::whereIn('id', $deductionComponentIds)->get()->keyBy('id');

                    // Calculate bruto from taxable earnings
                    $bruto = 0;
                    foreach ($calcData['earnings'] as $earning) {
                        $component = $components->get($earning['component_id']);
                        if ($component && $component->taxable) {
                            $bruto += (float) $earning['amount'];
                        }
                    }

                    // Calculate deductions from deduction components
                    $biayaJabatan = null;
                    $iuranPensiun = null;
                    $zakat = 0.0;

                    foreach ($calcData['deductions'] ?? [] as $deduction) {
                        $deductionComponent = $deductionComponents->get($deduction['deduction_component_id']);
                        if (!$deductionComponent) {
                            continue;
                        }

                        $amount = (float) $deduction['amount'];

                        // Identify special deductions
                        $isBiayaJabatan = $deductionComponent->code === 'biaya_jabatan' 
                            || (str_contains(strtolower($deductionComponent->name), 'biaya jabatan')
                                && $deductionComponent->calculation_type === 'auto');
                        
                        $isIuranPensiun = $deductionComponent->code === 'iuran_pensiun'
                            || (str_contains(strtolower($deductionComponent->name), 'iuran pensiun')
                            || ($deductionComponent->type === 'mandatory' && $deductionComponent->calculation_type === 'auto'));
                        
                        $isZakat = $deductionComponent->code === 'zakat'
                            || str_contains(strtolower($deductionComponent->name), 'zakat');

                        if ($isBiayaJabatan) {
                            $biayaJabatan = $amount;
                        } elseif ($isIuranPensiun) {
                            $iuranPensiun = $amount;
                        } elseif ($isZakat) {
                            $zakat = $amount;
                        }
                    }

                    // Auto-calculate biaya_jabatan and iuran_pensiun if not provided
                    if ($biayaJabatan === null) {
                        $biayaJabatanComponent = $deductionComponents->first(function ($dc) {
                            return $dc->code === 'biaya_jabatan' 
                                || (str_contains(strtolower($dc->name), 'biaya jabatan') && $dc->calculation_type === 'auto');
                        });
                        if ($biayaJabatanComponent) {
                            // Will be calculated by calculateStandalone if null
                            $biayaJabatan = null;
                        }
                    }

                    if ($iuranPensiun === null) {
                        $iuranPensiunComponent = $deductionComponents->first(function ($dc) {
                            return $dc->code === 'iuran_pensiun'
                                || (str_contains(strtolower($dc->name), 'iuran pensiun') && $dc->calculation_type === 'auto');
                        });
                        if ($iuranPensiunComponent) {
                            // Will be calculated by calculateStandalone if null
                            $iuranPensiun = null;
                        }
                    }

                    // Ensure zakat is never negative
                    $zakat = max(0.0, $zakat);
                } else {
                    // Old format: use bruto, biaya_jabatan, iuran_pensiun, zakat
                    // Safe casting for optional fields
                    $biayaJabatan = isset($calcData['biaya_jabatan']) && $calcData['biaya_jabatan'] !== null && $calcData['biaya_jabatan'] !== '' 
                        ? (float) $calcData['biaya_jabatan'] 
                        : null;
                    $iuranPensiun = isset($calcData['iuran_pensiun']) && $calcData['iuran_pensiun'] !== null && $calcData['iuran_pensiun'] !== '' 
                        ? (float) $calcData['iuran_pensiun'] 
                        : null;
                    
                    // Safe zakat casting: treat null, empty, or missing as 0
                    $zakat = 0.0;
                    if (isset($calcData['zakat']) && $calcData['zakat'] !== null && $calcData['zakat'] !== '') {
                        $zakat = (float) $calcData['zakat'];
                    }
                    $zakat = max(0.0, $zakat);
                    $bruto = (float) $calcData['bruto'];
                }

                $result = $calculator->calculateStandalone(
                    ptkpCode: $ptkpCode,
                    bruto: $bruto,
                    biayaJabatan: $biayaJabatan,
                    iuranPensiun: $iuranPensiun,
                    zakat: $zakat,
                    month: $month,
                    hasNpwp: $hasNpwp
                );

                $results[] = [
                    'employment_id' => $employment->id,
                    'person_name' => $employment->person->full_name ?? 'N/A',
                    'ptkp_code' => $ptkpCode,
                    'has_npwp' => $hasNpwp,
                    ...$result,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'employment_id' => $employment->id,
                    'person_name' => $employment->person->full_name ?? 'N/A',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'month' => $month,
            'total' => count($validated['calculations']),
            'success' => count(array_filter($results, fn($r) => !isset($r['error']))),
            'failed' => count(array_filter($results, fn($r) => isset($r['error']))),
            'results' => $results,
        ]);
    }

    /**
     * Search employees for calculator
     */
    public function searchEmployees(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);

        $query = Employment::query()
            ->with([
                'person.identifiers.scheme',
                'orgUnit',
                'payrollSubject',
            ])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });

        // Search by name or NIK
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('person', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        // Filter by org unit
        if ($request->has('org_unit_id')) {
            $query->where('org_unit_id', $request->input('org_unit_id'));
        }

        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
        $employments = $query->orderBy('start_date', 'desc')->paginate($perPage, ['*'], 'page', $page);

        // Format response
        $formatted = $employments->map(function ($employment) {
            return [
                'id' => $employment->id,
                'person_name' => $employment->person->full_name ?? 'N/A',
                'nik' => $employment->person->nik ?? 'N/A',
                'org_unit' => $employment->orgUnit->name ?? 'N/A',
                'employment_type' => $employment->employment_type,
                'ptkp_code' => $employment->payrollSubject?->ptkp_code ?? 'TK0',
                'has_npwp' => $employment->payrollSubject?->has_npwp ?? true,
            ];
        });

        return response()->json([
            'data' => $formatted,
            'current_page' => $employments->currentPage(),
            'last_page' => $employments->lastPage(),
            'per_page' => $employments->perPage(),
            'total' => $employments->total(),
        ]);
    }

    /**
     * Save calculation to history
     */
    public function saveHistory(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
        $userId = $request->user()->id;

        $validated = $request->validate([
            'calculations' => 'required|array|min:1',
            'calculations.*.employment_id' => 'nullable|exists:employments,id',
            'calculations.*.person_name' => 'required|string',
            'calculations.*.ptkp_code' => 'required|string|in:TK0,TK1,TK2,TK3,K0,K1,K2,K3',
            'calculations.*.has_npwp' => 'required|boolean',
            'calculations.*.year' => 'required|integer|min:2000|max:2100',
            'calculations.*.month' => 'required|integer|min:1|max:12',
            'calculations.*.calculation_mode' => 'nullable|in:monthly,yearly',
            'calculations.*.bruto' => 'required|numeric|min:0',
            'calculations.*.biaya_jabatan' => 'required|numeric|min:0',
            'calculations.*.iuran_pensiun' => 'required|numeric|min:0',
            'calculations.*.zakat' => 'required|numeric|min:0',
            'calculations.*.neto_masa' => 'required|numeric',
            'calculations.*.ptkp_yearly' => 'required|numeric',
            'calculations.*.pkp_annualized' => 'required|numeric',
            'calculations.*.pph21_masa' => 'required|numeric',
            'calculations.*.notes' => 'nullable|array',
            'calculations.*.earnings_breakdown' => 'nullable|array',
            'calculations.*.earnings_breakdown.*.component_id' => 'required_with:calculations.*.earnings_breakdown|exists:components,id',
            'calculations.*.earnings_breakdown.*.monthly_amount' => 'required_with:calculations.*.earnings_breakdown|numeric|min:0',
            'calculations.*.earnings_breakdown.*.annual_amount' => 'required_with:calculations.*.earnings_breakdown|numeric|min:0',
            'calculations.*.deductions_breakdown' => 'nullable|array',
            'calculations.*.deductions_breakdown.*.deduction_component_id' => 'required_with:calculations.*.deductions_breakdown|exists:deduction_components,id',
            'calculations.*.deductions_breakdown.*.monthly_amount' => 'required_with:calculations.*.deductions_breakdown|numeric|min:0',
            'calculations.*.deductions_breakdown.*.annual_amount' => 'required_with:calculations.*.deductions_breakdown|numeric|min:0',
        ]);

        $saved = [];

        foreach ($validated['calculations'] as $calcData) {
            $history = CalculationHistory::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'employment_id' => $calcData['employment_id'] ?? null,
                'person_name' => $calcData['person_name'],
                'ptkp_code' => $calcData['ptkp_code'],
                'has_npwp' => $calcData['has_npwp'],
                'year' => $calcData['year'],
                'month' => $calcData['month'],
                'calculation_mode' => $calcData['calculation_mode'] ?? 'monthly',
                'bruto' => $calcData['bruto'],
                'biaya_jabatan' => $calcData['biaya_jabatan'],
                'iuran_pensiun' => $calcData['iuran_pensiun'],
                'zakat' => $calcData['zakat'],
                'neto_masa' => $calcData['neto_masa'],
                'ptkp_yearly' => $calcData['ptkp_yearly'],
                'pkp_annualized' => $calcData['pkp_annualized'],
                'pph21_masa' => $calcData['pph21_masa'],
                'notes' => $calcData['notes'] ?? null,
                'earnings_breakdown' => $calcData['earnings_breakdown'] ?? null,
                'deductions_breakdown' => $calcData['deductions_breakdown'] ?? null,
            ]);

            $saved[] = $history;
        }

        return response()->json([
            'message' => 'Calculation saved to history',
            'saved_count' => count($saved),
            'history' => $saved,
        ], 201);
    }

    /**
     * Get calculation history
     */
    public function getHistory(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
        $userId = $request->user()->id;

        $query = CalculationHistory::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by year
        if ($request->has('year')) {
            $query->where('year', $request->input('year'));
        }

        // Filter by month
        if ($request->has('month')) {
            $query->where('month', $request->input('month'));
        }

        // Filter by employment
        if ($request->has('employment_id')) {
            $query->where('employment_id', $request->input('employment_id'));
        }

        $perPage = $request->input('per_page', 20);
        $history = $query->paginate($perPage);

        return response()->json($history);
    }

    /**
     * Get history summary by year
     */
    public function getHistorySummary(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
        $userId = $request->user()->id;

        $summary = CalculationHistory::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->selectRaw('year, COUNT(*) as count, SUM(bruto) as total_bruto, SUM(pph21_masa) as total_pph21')
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->get();

        return response()->json($summary);
    }

    /**
     * Get employee history summary list (for Page 1: Employee List)
     * Returns one row per employee per year with summary data
     */
    public function getEmployeeHistoryList(Request $request)
    {
        $tenantId = $request->input('tenant_id') 
            ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
        $userId = $request->user()->id;

        $query = CalculationHistory::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId);

        // Filter by year if provided
        if ($request->has('year')) {
            $query->where('year', $request->input('year'));
        }

        // Group by employment_id and year to get one row per employee per year
        $history = $query
            ->selectRaw('
                employment_id,
                person_name,
                ptkp_code,
                has_npwp,
                year,
                MAX(created_at) as latest_calculation_date,
                COUNT(*) as calculation_count,
                MIN(month) as first_month,
                MAX(month) as last_month,
                SUM(bruto) as total_bruto_ytd,
                SUM(neto_masa) as total_neto_ytd,
                SUM(pph21_masa) as total_pph21_ytd,
                SUM(pkp_annualized) as total_pkp_ytd
            ')
            ->whereNotNull('employment_id')
            ->groupBy('employment_id', 'person_name', 'ptkp_code', 'has_npwp', 'year')
            ->orderBy('year', 'desc')
            ->orderBy('person_name', 'asc')
            ->get();

        // Format response
        $formatted = $history->map(function ($item) {
            // Determine status text
            $statusText = 'Belum ada perhitungan';
            if ($item->calculation_count > 0) {
                $months = [];
                if ($item->first_month <= $item->last_month) {
                    for ($m = $item->first_month; $m <= $item->last_month; $m++) {
                        $months[] = $m;
                    }
                }
                
                if (count($months) === 12) {
                    $statusText = 'Dihitung untuk seluruh tahun';
                } elseif ($item->last_month === 12) {
                    $statusText = 'Dihitung sampai Desember';
                } elseif ($item->last_month === 11) {
                    $statusText = 'Dihitung sampai November';
                } else {
                    $monthNames = [
                        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                    ];
                    $statusText = 'Dihitung sampai ' . ($monthNames[$item->last_month] ?? 'Bulan ' . $item->last_month);
                }
            }

            return [
                'employment_id' => $item->employment_id,
                'person_name' => $item->person_name,
                'ptkp_code' => $item->ptkp_code,
                'has_npwp' => (bool) $item->has_npwp,
                'year' => $item->year,
                'latest_calculation_date' => $item->latest_calculation_date,
                'status_text' => $statusText,
                'calculation_count' => $item->calculation_count,
                'total_bruto_ytd' => (float) $item->total_bruto_ytd,
                'total_neto_ytd' => (float) $item->total_neto_ytd,
                'total_pph21_ytd' => (float) $item->total_pph21_ytd,
                'total_pkp_ytd' => (float) $item->total_pkp_ytd,
            ];
        });

        return response()->json([
            'data' => $formatted,
        ]);
    }

    /**
     * Get employee history detail with monthly breakdown (for Page 2: Employee Detail)
     * Returns employee profile + periods array for FullCalendar integration
     */
    public function getEmployeeHistoryDetail(Request $request, $employmentId)
    {
        try {
            $tenantId = $request->input('tenant_id') 
                ?? (app()->bound('tenant_id') ? app('tenant_id') : null);
            $userId = $request->user()->id;
            $year = $request->input('year', date('Y'));

            // Get employment with relations
            $employment = Employment::with(['person.identifiers.scheme', 'payrollSubject'])
                ->where('tenant_id', $tenantId)
                ->where('id', $employmentId)
                ->firstOrFail();

            // Get all calculations for this employment and year
            // Group by month and get the latest calculation per month (in case of duplicates)
            $calculations = CalculationHistory::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('employment_id', $employmentId)
                ->where('year', $year)
                ->orderBy('month', 'asc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('month')
                ->map(function ($group) {
                    return $group->first(); // Get latest calculation for each month
                });

            // Get employee profile data
            $person = $employment->person;
            $payrollSubject = $employment->payrollSubject;

            // Get NIP from identifiers if available
            $nip = null;
            if ($person && $person->identifiers) {
                $nipIdentifier = $person->identifiers->first(function ($identifier) {
                    return $identifier->scheme && strtoupper($identifier->scheme->code) === 'NIP';
                });
                if ($nipIdentifier) {
                    $nip = $nipIdentifier->raw_value;
                }
            }

            // Get NPWP number if available
            $npwpNumber = null;
            if ($person && $person->identifiers) {
                $npwpIdentifier = $person->identifiers->first(function ($identifier) {
                    return $identifier->scheme && strtoupper($identifier->scheme->code) === 'NPWP';
                });
                if ($npwpIdentifier) {
                    $npwpNumber = $npwpIdentifier->raw_value;
                }
            }

            // Build periods array (only months with calculations)
            $periods = [];
            $totalPph21Ytd = 0;
            $totalPph21Year = 0;
            $totalPkpYear = 0;
            $calculatedMonths = [];

            foreach ($calculations as $calc) {
                $month = (int) $calc->month;
                $isReconciliation = $month === 12;
                
                $pph21Masa = (float) $calc->pph21_masa;
                $totalPph21Ytd += $pph21Masa;
                
                // For December reconciliation, use YTD as the final year total
                if ($isReconciliation) {
                    $totalPph21Year = $totalPph21Ytd;
                }
                
                $totalPkpYear += (float) $calc->pkp_annualized;
                $calculatedMonths[] = $month;

                // Use created_at as calculation_date, format as YYYY-MM-DD
                $calculationDate = $calc->created_at ? $calc->created_at->format('Y-m-d') : 
                    sprintf('%d-%02d-28', $year, $month);

                // Calculate neto_annualized
                // For calculator standalone: neto_masa is already annual, so neto_annualized = neto_masa
                // For payroll: neto_masa is monthly, so neto_annualized = neto_masa * 12 (except Dec)
                // We detect by checking if bruto > 50M (likely annual from calculator) or <= 50M (likely monthly from payroll)
                $bruto = (float) $calc->bruto;
                $isFromCalculator = $bruto > 50000000; // Calculator standalone uses annual bruto
                
                if ($isFromCalculator) {
                    // From calculator standalone: neto_masa is already annual
                    $netoAnnualized = (float) $calc->neto_masa;
                } else {
                    // From payroll: neto_masa is monthly, need to annualize (except Dec reconciliation)
                    $netoAnnualized = $isReconciliation 
                        ? (float) $calc->neto_masa 
                        : (float) $calc->neto_masa * 12;
                }

                // Build notes array
                $notes = $calc->notes ?? [];
                if (empty($notes)) {
                    if ($isReconciliation) {
                        $notes = ['Perhitungan Desember: Rekonsiliasi tahunan'];
                    } else {
                        $notes = ['Perhitungan menggunakan TER (Tarif Efektif Rata-rata)'];
                    }
                    if ($payrollSubject && $payrollSubject->has_npwp) {
                        $notes[] = 'NPWP: Ya';
                    } else {
                        $notes[] = 'NPWP: Tidak';
                    }
                }

                $periods[] = [
                    'month' => $month,
                    'year' => (int) $year,
                    'calculation_date' => $calculationDate,
                    'bruto' => (float) $calc->bruto,
                    'biaya_jabatan' => (float) $calc->biaya_jabatan,
                    'iuran_pensiun' => (float) $calc->iuran_pensiun,
                    'zakat' => (float) $calc->zakat,
                    'neto_masa' => (float) $calc->neto_masa,
                    'neto_annualized' => $netoAnnualized,
                    'ptkp_yearly' => (float) $calc->ptkp_yearly,
                    'pkp_annualized' => (float) $calc->pkp_annualized,
                    'pph21_masa' => $pph21Masa,
                    'pph21_ytd' => $totalPph21Ytd,
                    'is_reconciliation' => $isReconciliation,
                    'notes' => $notes,
                ];
            }

            // Sort periods by month ascending
            usort($periods, fn($a, $b) => $a['month'] <=> $b['month']);

            // Build summary
            $summary = [
                'months_with_calculation' => $calculatedMonths,
                'total_pph21_ytd' => $totalPph21Ytd,
                'total_pph21_year' => $totalPph21Year > 0 ? $totalPph21Year : $totalPph21Ytd,
                'total_pkp_year' => $totalPkpYear,
            ];

            return response()->json([
                'employee' => [
                    'employment_id' => $employment->id,
                    'name' => $person->full_name ?? 'N/A',
                    'nip' => $nip,
                    'ptkp_code' => $payrollSubject->ptkp_code ?? 'TK0',
                    'has_npwp' => $payrollSubject->has_npwp ?? true,
                    'position' => $employment->employment_type ?? null,
                    'npwp_number' => $npwpNumber,
                ],
                'year' => (int) $year,
                'summary' => $summary,
                'periods' => $periods,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getEmployeeHistoryDetail: ' . $e->getMessage(), [
                'employment_id' => $employmentId,
                'year' => $request->input('year'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to get employee history detail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
