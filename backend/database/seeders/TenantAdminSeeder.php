<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantAdminSeeder extends Seeder
{
    /**
     * Seed the tenant admin user.
     *
     * @return void
     */
    public function run()
    {
        if (! tenant()) {
            return;
        }

        $tenantKey = (string) tenant()->getTenantKey();
        $password = env('TENANT_ADMIN_PASSWORD', 'admin123');

        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin',
                'email' => 'admin+' . $tenantKey . '@tenant.local',
                'password' => Hash::make($password),
                'status' => 'Active',
            ]
        );
    }
}
