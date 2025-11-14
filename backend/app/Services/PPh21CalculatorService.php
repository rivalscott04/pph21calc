<?php

namespace App\Services;

use App\Models\Employment;
use App\Models\Period;
use Illuminate\Support\Collection;

class PPh21CalculatorService
{
    // PTKP values (per tahun 2024)
    private const PTKP_VALUES = [
        'TK0' => 54000000,  // Tidak Kawin 0 tanggungan
        'TK1' => 58500000,  // Tidak Kawin 1 tanggungan
        'TK2' => 63000000,  // Tidak Kawin 2 tanggungan
        'TK3' => 67500000,  // Tidak Kawin 3 tanggungan
        'K0' => 58500000,   // Kawin 0 tanggungan
        'K1' => 63000000,   // Kawin 1 tanggungan
        'K2' => 67500000,   // Kawin 2 tanggungan
        'K3' => 72000000,   // Kawin 3 tanggungan
    ];

    // Tarif Pajak Pasal 17 (progresif)
    private const TAX_BRACKETS = [
        ['min' => 0, 'max' => 60000000, 'rate' => 0.05],
        ['min' => 60000000, 'max' => 250000000, 'rate' => 0.15],
        ['min' => 250000000, 'max' => 500000000, 'rate' => 0.25],
        ['min' => 500000000, 'max' => 5000000000, 'rate' => 0.30],
        ['min' => 5000000000, 'max' => PHP_INT_MAX, 'rate' => 0.35],
    ];

    // Biaya Jabatan: 5% dari bruto, maksimal 500.000/bulan atau 6.000.000/tahun
    private const BIAYA_JABATAN_RATE = 0.05;
    private const BIAYA_JABATAN_MAX_MONTHLY = 500000;
    private const BIAYA_JABATAN_MAX_YEARLY = 6000000;

    // Iuran Pensiun: 5% dari bruto, maksimal 200.000/bulan atau 2.400.000/tahun
    private const IURAN_PENSIUN_RATE = 0.05;
    private const IURAN_PENSIUN_MAX_MONTHLY = 200000;
    private const IURAN_PENSIUN_MAX_YEARLY = 2400000;

    /**
     * Calculate PPh21 for a single employment in a period
     */
    public function calculate(Employment $employment, Period $period, Collection $earnings, Collection $deductions): array
    {
        $payrollSubject = $employment->payrollSubject;
        if (!$payrollSubject || !$payrollSubject->active) {
            throw new \Exception('Active payroll subject not found for employment');
        }

        // 1. Calculate Bruto (sum of taxable earnings)
        $bruto = $this->calculateBruto($earnings);

        // 2. Calculate deductions
        $biayaJabatan = $this->calculateBiayaJabatan($bruto, $period->month);
        $iuranPensiun = $this->calculateIuranPensiun($bruto, $deductions, $period->month);
        $zakat = $this->calculateZakat($deductions);

        // 3. Calculate Neto Masa
        $netoMasa = $bruto - $biayaJabatan - $iuranPensiun - $zakat;

        // 4. Get PTKP
        $ptkpCode = $payrollSubject->ptkp_code;
        $ptkpYearly = $this->getPtkpValue($ptkpCode);

        // 5. Calculate PPh21 based on month
        if ($period->month == 12) {
            // December: Annual calculation with reconciliation
            return $this->calculateAnnual($employment, $period, $netoMasa, $ptkpYearly, $bruto, $biayaJabatan, $iuranPensiun, $zakat);
        } else {
            // January-November: Use TER (Tarif Efektif Rata-rata)
            return $this->calculateMonthly($employment, $period, $netoMasa, $ptkpYearly, $bruto, $biayaJabatan, $iuranPensiun, $zakat);
        }
    }

    /**
     * Calculate PPh21 for standalone calculator (manual input)
     * 
     * @param string $ptkpCode PTKP code (TK0, K0, K1, etc.)
     * @param float $bruto Total gross income
     * @param float $biayaJabatan Biaya jabatan (optional, will be calculated if not provided)
     * @param float $iuranPensiun Iuran pensiun (optional, will be calculated if not provided)
     * @param float $zakat Zakat amount
     * @param int $month Month (1-12, default: 11 for monthly calculation)
     * @param bool $hasNpwp Whether employee has NPWP
     * @return array Calculation result
     */
    public function calculateStandalone(
        string $ptkpCode,
        float $bruto,
        ?float $biayaJabatan = null,
        ?float $iuranPensiun = null,
        float $zakat = 0,
        int $month = 11,
        bool $hasNpwp = true
    ): array {
        // Calculate deductions if not provided
        if ($biayaJabatan === null) {
            $biayaJabatan = $this->calculateBiayaJabatan($bruto, $month);
        } else {
            // Cap biaya jabatan if provided manually
            $biayaJabatan = min($biayaJabatan, self::BIAYA_JABATAN_MAX_MONTHLY);
        }
        
        if ($iuranPensiun === null) {
            // Create dummy collection for deductions
            $deductions = collect();
            $iuranPensiun = $this->calculateIuranPensiun($bruto, $deductions, $month);
        } else {
            // Cap iuran pensiun if provided manually
            $iuranPensiun = min($iuranPensiun, self::IURAN_PENSIUN_MAX_MONTHLY);
        }

        // Calculate Neto Masa
        $netoMasa = $bruto - $biayaJabatan - $iuranPensiun - $zakat;

        // Get PTKP
        $ptkpYearly = $this->getPtkpValue($ptkpCode);

        // Calculate PPh21 (always use monthly calculation for standalone)
        $netoAnnualized = $netoMasa * 12;
        $pkpAnnualized = max(0, $netoAnnualized - $ptkpYearly);
        $pph21Annual = $this->calculateProgressiveTax($pkpAnnualized);
        $pph21Masa = $pph21Annual / 12;

        // Apply NPWP penalty if no NPWP (20% higher)
        if (!$hasNpwp) {
            $pph21Masa = $pph21Masa * 1.2;
        }

        // Generate notes
        $notes = $this->generateNotes($ptkpCode, $hasNpwp, $month);

        return [
            'bruto' => round($bruto, 2),
            'biaya_jabatan' => round($biayaJabatan, 2),
            'iuran_pensiun' => round($iuranPensiun, 2),
            'zakat' => round($zakat, 2),
            'neto_masa' => round($netoMasa, 2),
            'ptkp_yearly' => $ptkpYearly,
            'pkp_annualized' => round($pkpAnnualized, 2),
            'pph21_masa' => round($pph21Masa, 2),
            'notes' => $notes,
        ];
    }

    /**
     * Generate calculation notes
     */
    private function generateNotes(string $ptkpCode, bool $hasNpwp, int $month): array
    {
        $notes = [];

        $notes[] = "PTKP: {$ptkpCode} (" . number_format($this->getPtkpValue($ptkpCode), 0, ',', '.') . " per tahun)";
        
        if (!$hasNpwp) {
            $notes[] = "Peringatan: Tidak memiliki NPWP, PPh21 dikenakan tarif 20% lebih tinggi";
        }

        if ($month == 12) {
            $notes[] = "Perhitungan Desember: Akan dilakukan rekonsiliasi tahunan";
        } else {
            $notes[] = "Perhitungan bulanan menggunakan TER (Tarif Efektif Rata-rata)";
        }

        $notes[] = "Biaya Jabatan: 5% dari bruto, maksimal 500.000/bulan";
        $notes[] = "Iuran Pensiun: 5% dari bruto, maksimal 200.000/bulan";

        return $notes;
    }

    /**
     * Calculate bruto from earnings (only taxable components)
     */
    private function calculateBruto(Collection $earnings): float
    {
        return $earnings->sum(function ($earning) {
            // Only sum taxable components
            if ($earning->component && $earning->component->taxable) {
                return (float) $earning->amount;
            }
            return 0;
        });
    }

    /**
     * Calculate biaya jabatan
     */
    private function calculateBiayaJabatan(float $bruto, int $month): float
    {
        $calculated = $bruto * self::BIAYA_JABATAN_RATE;
        
        // Check monthly limit
        if ($calculated > self::BIAYA_JABATAN_MAX_MONTHLY) {
            return self::BIAYA_JABATAN_MAX_MONTHLY;
        }

        return $calculated;
    }

    /**
     * Calculate iuran pensiun (from deductions or calculated)
     */
    private function calculateIuranPensiun(float $bruto, Collection $deductions, int $month): float
    {
        // Check if iuran_pensiun is already in deductions
        $iuranPensiunFromDeductions = $deductions->where('type', 'iuran_pensiun')->sum('amount');
        
        if ($iuranPensiunFromDeductions > 0) {
            // Use the deduction amount, but cap it
            $maxMonthly = self::IURAN_PENSIUN_MAX_MONTHLY;
            return min($iuranPensiunFromDeductions, $maxMonthly);
        }

        // Calculate if not provided
        $calculated = $bruto * self::IURAN_PENSIUN_RATE;
        return min($calculated, self::IURAN_PENSIUN_MAX_MONTHLY);
    }

    /**
     * Calculate zakat from deductions
     */
    private function calculateZakat(Collection $deductions): float
    {
        return (float) $deductions->where('type', 'zakat')->sum('amount');
    }

    /**
     * Get PTKP value
     */
    private function getPtkpValue(string $ptkpCode): float
    {
        return self::PTKP_VALUES[$ptkpCode] ?? self::PTKP_VALUES['TK0'];
    }

    /**
     * Calculate monthly PPh21 using TER (Tarif Efektif Rata-rata)
     * For January-November
     */
    private function calculateMonthly(
        Employment $employment,
        Period $period,
        float $netoMasa,
        float $ptkpYearly,
        float $bruto,
        float $biayaJabatan,
        float $iuranPensiun,
        float $zakat
    ): array {
        // Annualize neto masa
        $netoAnnualized = $netoMasa * 12;
        $pkpAnnualized = max(0, $netoAnnualized - $ptkpYearly);

        // Calculate annual PPh21
        $pph21Annual = $this->calculateProgressiveTax($pkpAnnualized);

        // Calculate monthly PPh21 (divide by 12)
        $pph21Masa = $pph21Annual / 12;

        // Get YTD PPh21 from previous periods
        $pph21Ytd = $this->getYtdPph21($employment, $period);

        return [
            'bruto' => $bruto,
            'biaya_jabatan' => $biayaJabatan,
            'iuran_pensiun' => $iuranPensiun,
            'zakat' => $zakat,
            'neto_masa' => $netoMasa,
            'ptkp_yearly' => $ptkpYearly,
            'pkp_annualized' => $pkpAnnualized,
            'pph21_masa' => round($pph21Masa, 2),
            'pph21_ytd' => $pph21Ytd,
            'pph21_settlement_dec' => 0,
        ];
    }

    /**
     * Calculate annual PPh21 with reconciliation
     * For December
     */
    private function calculateAnnual(
        Employment $employment,
        Period $period,
        float $netoMasa,
        float $ptkpYearly,
        float $bruto,
        float $biayaJabatan,
        float $iuranPensiun,
        float $zakat
    ): array {
        // Get YTD data from previous periods (Jan-Nov)
        $ytdData = $this->getYtdData($employment, $period);

        // Calculate total neto for the year
        $netoYearly = $ytdData['neto_yearly'] + $netoMasa;

        // Calculate PKP yearly
        $pkpYearly = max(0, $netoYearly - $ptkpYearly);

        // Calculate annual PPh21
        $pph21Yearly = $this->calculateProgressiveTax($pkpYearly);

        // Get YTD PPh21 (Jan-Nov)
        $pph21Ytd = $ytdData['pph21_ytd'];

        // Calculate December settlement
        $pph21SettlementDec = $pph21Yearly - $pph21Ytd;

        // December PPh21 is the settlement amount
        $pph21Masa = max(0, $pph21SettlementDec);

        return [
            'bruto' => $bruto,
            'biaya_jabatan' => $biayaJabatan,
            'iuran_pensiun' => $iuranPensiun,
            'zakat' => $zakat,
            'neto_masa' => $netoMasa,
            'ptkp_yearly' => $ptkpYearly,
            'pkp_annualized' => $pkpYearly, // For December, this is the actual yearly PKP
            'pph21_masa' => round($pph21Masa, 2),
            'pph21_ytd' => $pph21Ytd,
            'pph21_settlement_dec' => round($pph21SettlementDec, 2),
        ];
    }

    /**
     * Calculate progressive tax using Pasal 17 brackets
     */
    private function calculateProgressiveTax(float $pkp): float
    {
        $totalTax = 0;
        $remainingPkp = $pkp;

        foreach (self::TAX_BRACKETS as $bracket) {
            if ($remainingPkp <= 0) {
                break;
            }

            $bracketAmount = min($remainingPkp, $bracket['max'] - $bracket['min']);
            $taxInBracket = $bracketAmount * $bracket['rate'];
            $totalTax += $taxInBracket;
            $remainingPkp -= $bracketAmount;
        }

        return $totalTax;
    }

    /**
     * Get YTD PPh21 from previous periods
     */
    private function getYtdPph21(Employment $employment, Period $period): float
    {
        $calculations = \App\Models\PayrollCalculation::where('employment_id', $employment->id)
            ->where('period_id', '!=', $period->id)
            ->whereHas('period', function ($query) use ($period) {
                $query->where('year', $period->year)
                      ->where('month', '<', $period->month);
            })
            ->get();

        return (float) $calculations->sum('pph21_masa');
    }

    /**
     * Get YTD data (neto and PPh21) from previous periods
     */
    private function getYtdData(Employment $employment, Period $period): array
    {
        $calculations = \App\Models\PayrollCalculation::where('employment_id', $employment->id)
            ->whereHas('period', function ($query) use ($period) {
                $query->where('year', $period->year)
                      ->where('month', '<', 12); // Jan-Nov
            })
            ->get();

        return [
            'neto_yearly' => (float) $calculations->sum('neto_masa'),
            'pph21_ytd' => (float) $calculations->sum('pph21_masa'),
        ];
    }
}

