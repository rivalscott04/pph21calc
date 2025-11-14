<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoretaxLog;
use App\Models\Period;
use App\Services\CoreTaxService;
use Illuminate\Http\Request;

class CoreTaxController extends Controller
{
    protected $coreTaxService;

    public function __construct(CoreTaxService $coreTaxService)
    {
        $this->coreTaxService = $coreTaxService;
    }

    /**
     * Export BPA1/BPA2 JSON for a period
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:periods,id',
        ]);

        $period = Period::with('tenant')->findOrFail($validated['period_id']);

        // Check if period is posted
        if ($period->status !== 'posted') {
            return response()->json([
                'message' => 'Period must be posted before export',
            ], 422);
        }

        try {
            $bpaData = $this->coreTaxService->generateBPA($period);

            return response()->json([
                'message' => 'BPA data generated successfully',
                'period' => [
                    'id' => $period->id,
                    'year' => $period->year,
                    'month' => $period->month,
                ],
                'bpa_data' => $bpaData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate BPA data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload BPA data to CoreTax
     */
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:periods,id',
        ]);

        $period = Period::with('tenant')->findOrFail($validated['period_id']);

        // Check if period is posted
        if ($period->status !== 'posted') {
            return response()->json([
                'message' => 'Period must be posted before upload',
            ], 422);
        }

        try {
            // Generate BPA data
            $bpaData = $this->coreTaxService->generateBPA($period);

            // Upload to CoreTax
            $uploadResult = $this->coreTaxService->uploadToCoreTax($bpaData);

            // Create log entry
            $log = CoretaxLog::create([
                'tenant_id' => $period->tenant_id,
                'period_id' => $period->id,
                'payload_json' => $bpaData,
                'status' => $uploadResult['success'] ? 'sent' : 'failed',
                'ref_no' => $uploadResult['ref_no'] ?? null,
                'response_json' => $uploadResult,
            ]);

            if ($uploadResult['success']) {
                return response()->json([
                    'message' => 'Data berhasil dikirim ke CoreTax',
                    'log_id' => $log->id,
                    'ref_no' => $uploadResult['ref_no'],
                    'status' => 'sent',
                ]);
            } else {
                return response()->json([
                    'message' => 'Gagal mengirim data ke CoreTax',
                    'log_id' => $log->id,
                    'errors' => $uploadResult['errors'] ?? [],
                    'status' => 'failed',
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload to CoreTax',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get CoreTax logs
     */
    public function logs(Request $request)
    {
        $query = CoretaxLog::with(['tenant', 'period'])
            ->orderBy('created_at', 'desc');

        // Filter by period if provided
        if ($request->has('period_id')) {
            $query->where('period_id', $request->period_id);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $logs = $query->paginate($request->get('per_page', 15));

        return response()->json($logs);
    }

    /**
     * Get specific CoreTax log
     */
    public function showLog($id)
    {
        $log = CoretaxLog::with(['tenant', 'period'])->findOrFail($id);

        return response()->json($log);
    }
}
