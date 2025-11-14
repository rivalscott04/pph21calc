<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use App\Models\TenantUser;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create superadmin
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@pph21.local',
            'password' => Hash::make('password'),
            'is_superadmin' => true,
            'status' => 'active',
        ]);

        // Create tenant
        $tenant = Tenant::create([
            'code' => 'TEST001',
            'name' => 'Test Company',
            'status' => 'active',
        ]);

        // Create tenant admin
        $tenantAdmin = User::create([
            'name' => 'Tenant Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
            'is_superadmin' => false,
            'status' => 'active',
        ]);

        TenantUser::create([
            'user_id' => $tenantAdmin->id,
            'tenant_id' => $tenant->id,
            'role' => 'TENANT_ADMIN',
            'status' => 'active',
        ]);

        // Create HR user
        $hrUser = User::create([
            'name' => 'HR User',
            'email' => 'hr@test.local',
            'password' => Hash::make('password'),
            'is_superadmin' => false,
            'status' => 'active',
        ]);

        TenantUser::create([
            'user_id' => $hrUser->id,
            'tenant_id' => $tenant->id,
            'role' => 'HR',
            'status' => 'active',
        ]);
    }
}
