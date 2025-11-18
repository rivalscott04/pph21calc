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
            // Komponen wajib HARUS selalu ada, jadi gunakan updateOrCreate
            
            // Komponen WAJIB - selalu di-ensure ada (create atau update)
            $mandatoryComponents = [
                [
                    'code' => 'iuran_pensiun',
                    'name' => 'Iuran Pensiun',
                    'type' => 'mandatory',
                    'calculation_type' => 'manual',
                    'is_tax_deductible' => true,
                    'priority' => 1,
                    'is_active' => true,
                    'notes' => 'Iuran pensiun yang dibayarkan pegawai ke dana pensiun. Maksimal 5% dari bruto atau 200.000/bulan. WAJIB sesuai peraturan PPh 21.',
                ],
                [
                    'code' => 'biaya_jabatan',
                    'name' => 'Biaya Jabatan',
                    'type' => 'mandatory',
                    'calculation_type' => 'auto',
                    'is_tax_deductible' => true,
                    'priority' => 0,
                    'is_active' => true,
                    'notes' => 'Biaya jabatan dihitung otomatis: 5% dari bruto, maksimal 500.000/bulan atau 6.000.000/tahun. WAJIB sesuai peraturan PPh 21.',
                ],
            ];

            $mandatoryCreated = 0;
            $mandatoryUpdated = 0;

            foreach ($mandatoryComponents as $component) {
                $existing = DeductionComponent::where('tenant_id', $tenant->id)
                    ->where('code', $component['code'])
                    ->first();

                if ($existing) {
                    // Update jika sudah ada untuk memastikan konfigurasi benar
                    $existing->update([
                        'name' => $component['name'],
                        'type' => $component['type'],
                        'calculation_type' => $component['calculation_type'],
                        'is_tax_deductible' => $component['is_tax_deductible'],
                        'priority' => $component['priority'],
                        'is_active' => $component['is_active'],
                        'notes' => $component['notes'],
                    ]);
                    $mandatoryUpdated++;
                } else {
                    // Create jika belum ada
                    DeductionComponent::create([
                        'tenant_id' => $tenant->id,
                        ...$component,
                    ]);
                    $mandatoryCreated++;
                }
            }

            // Komponen OPSIONAL - hanya dibuat jika belum ada
            $optionalComponents = [
                [
                    'code' => 'zakat',
                    'name' => 'Zakat',
                    'type' => 'custom',
                    'calculation_type' => 'manual',
                    'is_tax_deductible' => true,
                    'priority' => 2,
                    'is_active' => true,
                    'notes' => 'Zakat yang dibayarkan pegawai ke lembaga amil zakat resmi. Opsional (tidak wajib).',
                ],
            ];

            $optionalCreated = 0;

            foreach ($optionalComponents as $component) {
                $existing = DeductionComponent::where('tenant_id', $tenant->id)
                    ->where('code', $component['code'])
                    ->first();

                if (!$existing) {
                    DeductionComponent::create([
                        'tenant_id' => $tenant->id,
                        ...$component,
                    ]);
                    $optionalCreated++;
                }
            }

            $this->command->info("Tenant {$tenant->name}: Mandatory components - {$mandatoryCreated} created, {$mandatoryUpdated} updated. Optional components - {$optionalCreated} created.");
        }
    }
}
