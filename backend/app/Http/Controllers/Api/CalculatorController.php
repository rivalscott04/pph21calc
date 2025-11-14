<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PPh21CalculatorService;
use Illuminate\Http\Request;

class CalculatorController extends Controller
{
    /**
     * Calculate PPh21 (standalone calculator)
     */
    public function calculatePph21(Request $request)
    {
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
            $result = $calculator->calculateStandalone(
                ptkpCode: $validated['ptkp_code'],
                bruto: $validated['bruto'],
                biayaJabatan: $validated['biaya_jabatan'] ?? null,
                iuranPensiun: $validated['iuran_pensiun'] ?? null,
                zakat: $validated['zakat'] ?? 0,
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
