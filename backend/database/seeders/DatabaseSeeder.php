<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        if (tenant()) {
            $this->call([
                TenantAdminSeeder::class,
                TenantCatalogSeeder::class,
            ]);

            return;
        }

        $this->call([
            CentralAdminSeeder::class,
        ]);
    }
}
