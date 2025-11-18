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
            // Menurut aturan PPh 21, komponen pengurang yang WAJIB adalah:
            // 1. Iuran Pensiun
            // 2. Biaya Jabatan
            // Komponen wajib HARUS selalu ada, langsung create (pakai updateOrCreate untuk safety)
            
            // Komponen WAJIB - langsung create/update
            DeductionComponent::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'code' => 'iuran_pensiun',
                ],
                [
                    'name' => 'Iuran Pensiun',
                    'type' => 'mandatory',
                    'calculation_type' => 'manual',
                    'is_tax_deductible' => true,
                    'priority' => 1,
                    'is_active' => true,
                    'notes' => 'Iuran pensiun yang dibayarkan pegawai ke dana pensiun. Maksimal 5% dari bruto atau 200.000/bulan. WAJIB sesuai peraturan PPh 21.',
                ]
            );

            DeductionComponent::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'code' => 'biaya_jabatan',
                ],
                [
                    'name' => 'Biaya Jabatan',
                    'type' => 'mandatory',
                    'calculation_type' => 'auto',
                    'is_tax_deductible' => true,
                    'priority' => 0,
                    'is_active' => true,
                    'notes' => 'Biaya jabatan dihitung otomatis: 5% dari bruto, maksimal 500.000/bulan atau 6.000.000/tahun. WAJIB sesuai peraturan PPh 21.',
                ]
            );

            // Komponen OPSIONAL - hanya dibuat jika belum ada
            DeductionComponent::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'code' => 'zakat',
                ],
                [
                    'name' => 'Zakat',
                    'type' => 'custom',
                    'calculation_type' => 'manual',
                    'is_tax_deductible' => true,
                    'priority' => 2,
                    'is_active' => true,
                    'notes' => 'Zakat yang dibayarkan pegawai ke lembaga amil zakat resmi. Opsional (tidak wajib).',
                ]
            );

            $this->command->info("Deduction components seeded for tenant: {$tenant->name}");
        }
    }
}
