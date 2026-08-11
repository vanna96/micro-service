<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CentralAdminSeeder extends Seeder
{
    /**
     * Seed the central administrator user.
     *
     * @return void
     */
    public function run()
    {
        if (tenant()) {
            return;
        }

        $username = env('CENTRAL_ADMIN_USERNAME', 'admin');
        $password = env('CENTRAL_ADMIN_PASSWORD', 'admin123');

        $admin = User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => env('CENTRAL_ADMIN_NAME', 'Administrator'),
                'email' => env('CENTRAL_ADMIN_EMAIL', 'admin@example.local'),
                'password' => Hash::make($password),
                'status' => 'Active',
            ]
        );

        $tenantIds = Tenant::query()
            ->where('status', 'Active')
            ->pluck('id')
            ->all();

        $admin->tenants()->syncWithoutDetaching($tenantIds);
    }
}
