<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('security_settings')) {
            return;
        }

        DB::table('security_settings')->insertOrIgnore([
            'key' => 'country_access_scope',
            'value' => 'storefront_only',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('security_settings')) {
            DB::table('security_settings')->where('key', 'country_access_scope')->delete();
        }
    }
};
