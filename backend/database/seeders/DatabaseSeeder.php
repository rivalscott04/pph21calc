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
            'email' => 'admin@tes.com',
            'password' => Hash::make('password'),
            'is_superadmin' => true,
            'status' => 'active',
        ]);

        $this->command->info('Superadmin created: admin@tes.com / password');

        // Create tenant: Bank NTB Syariah
        $tenant = Tenant::create([
            'code' => 'BNTB',
            'name' => 'Bank NTB Syariah',
            'npwp_pemotong' => null,
            'status' => 'active',
        ]);

        $this->command->info('Tenant created: Bank NTB Syariah (BNTB)');

        // Create tenant admin for Bank NTB Syariah
        $tenantAdmin = User::create([
            'name' => 'Admin Bank NTB Syariah',
            'email' => 'ntbs@mail.com',
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

        $this->command->info('Tenant Admin created: ntbs@mail.com / password');
        $this->command->info('');
        $this->command->info('=== Login Credentials ===');
        $this->command->info('Superadmin: admin@tes.com / password');
        $this->command->info('Tenant Admin: ntbs@mail.com / password');
    }
}
