<?php

namespace Tests\Unit;

use App\Models\Employment;
use App\Models\Period;
use App\Models\PayrollSubject;
use App\Services\PPh21CalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Collection;

class PPh21CalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PPh21CalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PPh21CalculatorService();
    }

    /**
     * Test standalone calculation for TK0 (Tidak Kawin, 0 Tanggungan)
     */
    public function test_standalone_calculation_tk0(): void
    {
        $result = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 10000000, // 10 juta
            zakat: 0,
            month: 11,
            hasNpwp: true
        );

        $this->assertIsArray($result);
        $this->assertEquals(10000000, $result['bruto']);
        $this->assertGreaterThan(0, $result['biaya_jabatan']);
        $this->assertLessThanOrEqual(500000, $result['biaya_jabatan']); // Max 500k
        $this->assertGreaterThan(0, $result['neto_masa']);
        $this->assertGreaterThan(0, $result['pph21_masa']);
        $this->assertArrayHasKey('notes', $result);
    }

    /**
     * Test biaya jabatan calculation (5% of bruto, max 500k)
     */
    public function test_biaya_jabatan_calculation(): void
    {
        // Test normal case (5% of bruto < 500k)
        $result1 = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 5000000, // 5 juta -> 5% = 250k
            month: 11,
            hasNpwp: true
        );
        $this->assertEquals(250000, $result1['biaya_jabatan']);

        // Test capped case (5% of bruto > 500k)
        $result2 = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 15000000, // 15 juta -> 5% = 750k, should cap at 500k
            month: 11,
            hasNpwp: true
        );
        $this->assertEquals(500000, $result2['biaya_jabatan']);
    }

    /**
     * Test iuran pensiun calculation (5% of bruto, max 200k)
     */
    public function test_iuran_pensiun_calculation(): void
    {
        // Test normal case
        $result1 = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 2000000, // 2 juta -> 5% = 100k
            month: 11,
            hasNpwp: true
        );
        $this->assertEquals(100000, $result1['iuran_pensiun']);

        // Test capped case
        $result2 = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 6000000, // 6 juta -> 5% = 300k, should cap at 200k
            month: 11,
            hasNpwp: true
        );
        $this->assertEquals(200000, $result2['iuran_pensiun']);
    }

    /**
     * Test neto masa calculation
     */
    public function test_neto_masa_calculation(): void
    {
        $result = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 10000000,
            biayaJabatan: 500000,
            iuranPensiun: 200000,
            zakat: 100000,
            month: 11,
            hasNpwp: true
        );

        $expectedNeto = 10000000 - 500000 - 200000 - 100000; // 9.2 juta
        $this->assertEquals($expectedNeto, $result['neto_masa']);
    }

    /**
     * Test PTKP values
     */
    public function test_ptkp_values(): void
    {
        $ptkpCodes = ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3'];
        
        foreach ($ptkpCodes as $code) {
            $result = $this->calculator->calculateStandalone(
                ptkpCode: $code,
                bruto: 10000000,
                month: 11,
                hasNpwp: true
            );
            
            $this->assertGreaterThan(0, $result['ptkp_yearly']);
            $this->assertIsNumeric($result['ptkp_yearly']);
        }
    }

    /**
     * Test PKP annualized calculation
     */
    public function test_pkp_annualized_calculation(): void
    {
        $result = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0', // PTKP = 54 juta
            bruto: 10000000,
            month: 11,
            hasNpwp: true
        );

        $netoAnnualized = $result['neto_masa'] * 12;
        $expectedPkp = max(0, $netoAnnualized - $result['ptkp_yearly']);
        
        $this->assertEquals($expectedPkp, $result['pkp_annualized']);
    }

    /**
     * Test NPWP penalty (20% higher for non-NPWP)
     */
    public function test_npwp_penalty(): void
    {
        $resultWithNpwp = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 10000000,
            month: 11,
            hasNpwp: true
        );

        $resultWithoutNpwp = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 10000000,
            month: 11,
            hasNpwp: false
        );

        // PPh21 without NPWP should be 20% higher
        $expectedPph21WithoutNpwp = $resultWithNpwp['pph21_masa'] * 1.2;
        $this->assertEqualsWithDelta($expectedPph21WithoutNpwp, $resultWithoutNpwp['pph21_masa'], 1);
    }

    /**
     * Test progressive tax brackets
     */
    public function test_progressive_tax_brackets(): void
    {
        // Test low income (should be in 5% bracket)
        $result1 = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 5000000, // Low income
            month: 11,
            hasNpwp: true
        );
        $this->assertGreaterThan(0, $result1['pph21_masa']);

        // Test high income (should use multiple brackets)
        $result2 = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 30000000, // High income
            month: 11,
            hasNpwp: true
        );
        $this->assertGreaterThan($result1['pph21_masa'], $result2['pph21_masa']);
    }

    /**
     * Test zakat deduction
     */
    public function test_zakat_deduction(): void
    {
        $resultWithZakat = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 10000000,
            zakat: 500000,
            month: 11,
            hasNpwp: true
        );

        $resultWithoutZakat = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 10000000,
            zakat: 0,
            month: 11,
            hasNpwp: true
        );

        // With zakat, neto should be lower
        $this->assertLessThan($resultWithoutZakat['neto_masa'], $resultWithZakat['neto_masa']);
        
        // With zakat, PPh21 should be lower
        $this->assertLessThan($resultWithoutZakat['pph21_masa'], $resultWithZakat['pph21_masa']);
    }

    /**
     * Test notes generation
     */
    public function test_notes_generation(): void
    {
        $result = $this->calculator->calculateStandalone(
            ptkpCode: 'TK0',
            bruto: 10000000,
            month: 11,
            hasNpwp: true
        );

        $this->assertIsArray($result['notes']);
        $this->assertGreaterThan(0, count($result['notes']));
        $this->assertStringContainsString('PTKP', $result['notes'][0]);
    }
}

