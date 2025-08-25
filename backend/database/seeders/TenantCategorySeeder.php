<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Tenant;

class TenantCategorySeeder extends Seeder
{
    public function run()
    {
        // Fake data for tenant 1
        $tenant1 = Tenant::where('id', '1')->first();
        $tenant1->run(function () {
            Category::insert([
                ['name' => 'Tenant1 - Electronics'],
                ['name' => 'Tenant1 - Books'],
                ['name' => 'Tenant1 - Clothing'],
            ]);
        });

        // Fake data for tenant 2
        $tenant2 = Tenant::where('id', '2')->first();
        $tenant2->run(function () {
            Category::insert([
                ['name' => 'Tenant2 - Furniture'],
                ['name' => 'Tenant2 - Toys'],
                ['name' => 'Tenant2 - Sports'],
            ]);
        });
    }
}
