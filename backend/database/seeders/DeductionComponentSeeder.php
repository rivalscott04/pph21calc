<?php

namespace Database\Seeders;

use App\Models\DeductionComponent;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeductionComponentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all tenants
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Check if deduction components already exist for this tenant
            $existing = DeductionComponent::where('tenant_id', $tenant->id)->count();
            if ($existing > 0) {
                $this->command->info("Skipping tenant {$tenant->name} - deduction components already exist");
                continue;
            }

            // Create default deduction components
            $defaultComponents = [
                [
                    'code' => 'iuran_pensiun',
                    'name' => 'Iuran Pensiun',
                    'type' => 'mandatory',
                    'calculation_type' => 'manual',
                    'is_tax_deductible' => true,
                    'priority' => 1,
                    'is_active' => true,
                    'notes' => 'Iuran pensiun yang dibayarkan pegawai ke dana pensiun. Maksimal 5% dari bruto atau 200.000/bulan.',
                ],
                [
                    'code' => 'zakat',
                    'name' => 'Zakat',
                    'type' => 'mandatory',
                    'calculation_type' => 'manual',
                    'is_tax_deductible' => true,
                    'priority' => 2,
                    'is_active' => true,
                    'notes' => 'Zakat yang dibayarkan pegawai ke lembaga amil zakat resmi.',
                ],
                [
                    'code' => 'biaya_jabatan',
                    'name' => 'Biaya Jabatan',
                    'type' => 'mandatory',
                    'calculation_type' => 'auto',
                    'is_tax_deductible' => true,
                    'priority' => 0,
                    'is_active' => true,
                    'notes' => 'Biaya jabatan dihitung otomatis: 5% dari bruto, maksimal 500.000/bulan atau 6.000.000/tahun.',
                ],
            ];

            foreach ($defaultComponents as $component) {
                DeductionComponent::create([
                    'tenant_id' => $tenant->id,
                    ...$component,
                ]);
            }

            $this->command->info("Created default deduction components for tenant: {$tenant->name}");
        }
    }
}
