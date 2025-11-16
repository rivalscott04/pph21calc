<?php

namespace App\Services;

use App\Models\Period;
use App\Models\PayrollCalculation;
use App\Models\Employment;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class CoreTaxService
{
    /**
     * Generate BPA1/BPA2 JSON format for a period
     * 
     * @param Period $period
     * @return array BPA format data
     */
    public function generateBPA(Period $period): array
    {
        $tenant = $period->tenant;
        $calculations = PayrollCalculation::where('period_id', $period->id)
            ->with(['employment.person', 'employment.payrollSubject'])
            ->get();

        $bpaData = [
            'header' => $this->generateHeader($tenant, $period),
            'data_pemotong' => $this->generatePemotongData($tenant),
            'data_pajak' => $this->generatePajakData($period, $calculations),
        ];

        return $bpaData;
    }

    /**
     * Generate header information
     */
    private function generateHeader(Tenant $tenant, Period $period): array
    {
        return [
            'tahun_pajak' => $period->year,
            'masa_pajak' => str_pad($period->month, 2, '0', STR_PAD_LEFT),
            'npwp_pemotong' => $tenant->npwp_pemotong ?? '',
            'nama_pemotong' => $tenant->name,
            'kode_pemotong' => $tenant->code,
        ];
    }

    /**
     * Generate pemotong (withholder) data
     */
    private function generatePemotongData(Tenant $tenant): array
    {
        return [
            'npwp' => $tenant->npwp_pemotong ?? '',
            'nama' => $tenant->name,
            'alamat' => '', // Add if tenant has address field
            'kode_cabang' => $tenant->code,
        ];
    }

    /**
     * Generate pajak data (list of employees with tax calculations)
     */
    private function generatePajakData(Period $period, Collection $calculations): array
    {
        $dataPajak = [];

        foreach ($calculations as $calc) {
            $employment = $calc->employment;
            $person = $employment->person;
            $payrollSubject = $employment->payrollSubject;

            // Determine BPA type based on employment type
            $bpaType = $this->determineBPAType($employment->employment_type);

            $item = [
                'npwp' => $person->npwp ?? '',
                'nik' => $person->nik ?? '',
                'nama' => $person->full_name,
                'alamat' => '', // Add if person has address field
                'status_ptkp' => $payrollSubject->ptkp_code ?? 'TK0',
                'penghasilan_bruto' => (float) $calc->bruto,
                'biaya_jabatan' => (float) $calc->biaya_jabatan,
                'iuran_pensiun' => (float) $calc->iuran_pensiun,
                'zakat' => (float) $calc->zakat,
                'penghasilan_neto' => (float) $calc->neto_masa,
                'ptkp_setahun' => (float) $calc->ptkp_yearly,
                'pkp_setahun' => (float) $calc->pkp_annualized,
                'pph21_dipotong' => (float) $calc->pph21_masa,
                'pph21_ytd' => (float) $calc->pph21_ytd,
                'pph21_settlement' => (float) $calc->pph21_settlement_dec,
                'masa_pajak' => str_pad($period->month, 2, '0', STR_PAD_LEFT),
                'tahun_pajak' => $period->year,
                'bpa_type' => $bpaType,
            ];

            $dataPajak[] = $item;
        }

        return $dataPajak;
    }

    /**
     * Determine BPA type based on employment type
     * BPA1 = tetap, BPA2 = tidak_tetap/harian/tenaga_ahli
     */
    private function determineBPAType(string $employmentType): string
    {
        return match($employmentType) {
            'tetap' => 'BPA1',
            'tidak_tetap', 'harian', 'tenaga_ahli' => 'BPA2',
            default => 'BPA1',
        };
    }

    /**
     * Validate BPA data before upload
     */
    public function validateBPA(array $bpaData): array
    {
        $errors = [];

        // Validate header
        if (empty($bpaData['header']['npwp_pemotong'])) {
            $errors[] = 'NPWP Pemotong is required';
        }

        // Validate data pajak
        if (empty($bpaData['data_pajak'])) {
            $errors[] = 'Data pajak is empty';
        }

        foreach ($bpaData['data_pajak'] as $index => $item) {
            if (empty($item['npwp']) && empty($item['nik'])) {
                $errors[] = "Data pajak index {$index}: NPWP or NIK is required";
            }
            if (empty($item['nama'])) {
                $errors[] = "Data pajak index {$index}: Nama is required";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Upload BPA data to CoreTax API
     * 
     * NOTE: This requires CoreTax API credentials and endpoint configuration.
     * Configure the following in .env:
     * - CORETAX_API_URL=https://api.coretax.djp.go.id
     * - CORETAX_API_KEY=your_api_key
     * - CORETAX_CLIENT_ID=your_client_id
     * - CORETAX_CLIENT_SECRET=your_client_secret
     * 
     * @param array $bpaData
     * @return array Response from CoreTax
     */
    public function uploadToCoreTax(array $bpaData): array
    {
        $validation = $this->validateBPA($bpaData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
            ];
        }

        // Get CoreTax configuration from environment
        $apiUrl = config('services.coretax.api_url');
        $apiKey = config('services.coretax.api_key');
        $clientId = config('services.coretax.client_id');
        $clientSecret = config('services.coretax.client_secret');

        // If CoreTax is not configured, return mock response for development
        if (empty($apiUrl) || empty($apiKey)) {
            \Log::warning('CoreTax API not configured, using mock response');
            
            // Mock successful upload for development/testing
            $refNo = 'CORETAX-MOCK-' . date('YmdHis') . '-' . rand(1000, 9999);
            
            return [
                'success' => true,
                'message' => 'Data berhasil dikirim ke CoreTax (MOCK - CoreTax API not configured)',
                'ref_no' => $refNo,
                'timestamp' => now()->toIso8601String(),
            ];
        }

        try {
            // TODO: Implement actual CoreTax API integration
            // Example implementation (uncomment and adjust when CoreTax API is available):
            /*
            $client = new \GuzzleHttp\Client([
                'base_uri' => $apiUrl,
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken($clientId, $clientSecret),
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $apiKey,
                ],
            ]);

            $response = $client->post('/api/v1/bpa/upload', [
                'json' => $bpaData,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => $response->getStatusCode() === 200,
                'message' => $responseData['message'] ?? 'Data berhasil dikirim ke CoreTax',
                'ref_no' => $responseData['ref_no'] ?? null,
                'timestamp' => now()->toIso8601String(),
                'response' => $responseData,
            ];
            */

            // Placeholder: Return error indicating API integration is needed
            return [
                'success' => false,
                'message' => 'CoreTax API integration not yet implemented. Please configure CoreTax credentials and uncomment the API call code.',
                'errors' => ['API integration pending'],
            ];
        } catch (\Exception $e) {
            \Log::error('CoreTax upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal mengirim data ke CoreTax: ' . $e->getMessage(),
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Get access token from CoreTax OAuth (if required)
     * 
     * @param string $clientId
     * @param string $clientSecret
     * @return string
     */
    private function getAccessToken(string $clientId, string $clientSecret): string
    {
        // TODO: Implement OAuth token retrieval from CoreTax
        // This is a placeholder method
        return '';
    }
}

