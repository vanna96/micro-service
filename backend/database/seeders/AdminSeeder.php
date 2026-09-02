<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\User::updateOrCreate(
            [
                'username' => 'admin',
                'email' => 'admin@gmail.com',
            ],
            [
                'name' => 'admin',
                'password' => bcrypt('*#Admin@123456'),
            ]
        );
    }
}
